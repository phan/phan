<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use ast;
use ast\Node;
use Phan\AST\Parser;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\FQSENException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Scope\ClassScope;
use Phan\Language\Type;
use Phan\Library\FileCache;
use Phan\Parse\ParseVisitor;
use Throwable;

/**
 * Finds the type aliases that the doc comment of a class declares (`(at)phpstan-type Name Type`),
 * for `(at)phpstan-import-type Name from ClassName` in another file.
 *
 * The file of ClassName may not have been parsed yet when the import is seen. In that case this looks for the file
 * that declares the class in the list of files to parse, and reads the alias declarations from the doc comments of its classes.
 * Nothing else of that file is recorded: it is parsed as usual when its turn comes.
 */
final class TypeAliasImporter
{
    /** @var array<string,true> the files that the aliases were read from */
    private static $scanned_files = [];

    /** @var array<string,true> the classes (lowercase FQSEN) that no file was found for */
    private static $missing_classes = [];

    /** @var int the depth of loadAliasesFromFile() calls (an imported class can import from another class) */
    private static $depth = 0;

    /**
     * @var array<string,array{0:FullyQualifiedClassName,1:Context,2:string,3:int}> the classes (by lowercase FQSEN) of the files that are being scanned
     * whose doc comment was not looked at yet: [FQSEN, context, doc comment, flags of the declaration].
     * (A class can import from a class that is declared further down in the same file)
     */
    private static $pending_classes = [];

    /** @var array<string,string> maps the files of the request of the daemon or language server to their contents, if not those on disk */
    private static $file_contents_overrides = [];

    /**
     * Forget what was scanned (for a new analysis in the same process)
     *
     * @param array<string,string> $file_contents_overrides maps files to their contents, where not those on disk
     */
    public static function reset(array $file_contents_overrides = []): void
    {
        self::$file_contents_overrides = $file_contents_overrides;
        self::$scanned_files = [];
        self::$missing_classes = [];
        self::$pending_classes = [];
    }

    /**
     * @param string $current_file the file with the import: the class may be declared further down in the same file
     * @return ?Type the type alias $alias_name that the doc comment of the class $class_fqsen declares
     */
    public static function getTypeAliasOfClass(CodeBase $code_base, FullyQualifiedClassName $class_fqsen, string $alias_name, string $current_file = ''): ?Type
    {
        $type = $code_base->getTypeAliasOfClass($class_fqsen, $alias_name);
        if ($type !== null || $code_base->hasTypeAliasesOfClass($class_fqsen)) {
            return $type;
        }
        $key = \strtolower($class_fqsen->__toString());
        if (isset(self::$pending_classes[$key])) {
            self::resolvePendingClass($code_base, $key);
            return $code_base->getTypeAliasOfClass($class_fqsen, $alias_name);
        }
        if (isset(self::$missing_classes[$key]) || self::$depth > 20) {
            return null;
        }
        $candidate_files = self::candidateFiles($code_base, $class_fqsen);
        if ($current_file !== '' && $current_file !== 'internal' && !isset(self::$scanned_files[$current_file])) {
            \array_unshift($candidate_files, $current_file);
        }
        if ($code_base->hasClassWithFQSEN($class_fqsen)) {
            // The class was parsed, but its aliases are not recorded (the daemon forgets them for each request): its file is known.
            $class = $code_base->getClassByFQSENWithoutHydrating($class_fqsen);
            $class_file = $class->getFileRef()->getFile();
            if (!$class->isPHPInternal() && !isset(self::$scanned_files[$class_file])) {
                \array_unshift($candidate_files, $class_file);
            }
        }
        foreach ($candidate_files as $file_path) {
            self::loadAliasesFromFile($code_base, $file_path);
            if ($code_base->hasTypeAliasesOfClass($class_fqsen)) {
                return $code_base->getTypeAliasOfClass($class_fqsen, $alias_name);
            }
        }
        self::$missing_classes[$key] = true;
        return null;
    }

    /**
     * @return list<string> the files that are likely to declare $class_fqsen, going by their names, most likely first:
     * `Name.php`, then names that contain the name of the class (`class-wp-filesystem-base.php` for `WP_Filesystem_Base`).
     * Among those, the files in directories named like the namespace of the class come first (`src/DTO/File.php` for `\App\DTO\File`).
     */
    private static function candidateFiles(CodeBase $code_base, FullyQualifiedClassName $class_fqsen): array
    {
        $name = \strtolower($class_fqsen->getName());
        $dashed_name = \str_replace('_', '-', $name);
        $namespace_parts = \array_reverse(\array_filter(\explode('\\', \strtolower($class_fqsen->getNamespace()))));
        // Maps the file to its rank: lower is more likely
        $ranks = [];
        foreach ($code_base->getFilePathListForTypeAliases() as $file_path) {
            if (isset(self::$scanned_files[$file_path])) {
                continue;
            }
            $base_name = \strtolower(\basename($file_path, '.php'));
            if ($base_name === $name) {
                $rank = 0;
            } elseif (\str_contains($base_name, $name) || \str_contains($base_name, $dashed_name)) {
                $rank = 100;
            } else {
                continue;
            }
            // Subtract the number of directories that match the namespace, from the innermost one.
            if ($namespace_parts) {
                $directories = \array_reverse(\explode('/', \strtolower(\str_replace('\\', '/', \dirname($file_path)))));
                foreach ($namespace_parts as $i => $part) {
                    if (($directories[$i] ?? null) !== $part) {
                        break;
                    }
                    $rank--;
                }
            }
            $ranks[$file_path] = $rank;
        }
        \asort($ranks);
        // A project may have many files with the name of a class: don't read more than a few of them for one import.
        return \array_slice(\array_keys($ranks), 0, 8);
    }

    /**
     * Records the type aliases that the doc comments of the classes in $file_path declare.
     */
    private static function loadAliasesFromFile(CodeBase $code_base, string $file_path): void
    {
        if (isset(self::$scanned_files[$file_path])) {
            // (A file can be in the list of candidates twice, or be scanned for an import of a class that is scanned)
            return;
        }
        self::$scanned_files[$file_path] = true;
        $old_is_discarding_issues = Issue::$is_discarding_issues;
        // Whatever is wrong with this file is reported when it is parsed.
        Issue::$is_discarding_issues = true;
        self::$depth++;
        try {
            // (In the daemon and the language server, a file can have contents that are not saved yet)
            $contents = self::$file_contents_overrides[$file_path] ?? FileCache::getOrReadEntry(Config::projectPath($file_path))->getContents();
            if (\stripos($contents, '-type') === false) {
                return;
            }
            $context = (new Context())->withFile($file_path);
            $root = Parser::parseCode($code_base, $context, null, $file_path, $contents, true);
            $keys = [];
            self::collectClasses($code_base, $context, $root, $keys);
            foreach ($keys as $key) {
                self::resolvePendingClass($code_base, $key);
            }
        } catch (Throwable) {
            // e.g. a file that cannot be read, or a syntax error
        } finally {
            self::$depth--;
            Issue::$is_discarding_issues = $old_is_discarding_issues;
        }
    }

    /**
     * Records the type aliases that the doc comment of a pending class declares.
     */
    private static function resolvePendingClass(CodeBase $code_base, string $key): void
    {
        $pending = self::$pending_classes[$key] ?? null;
        if ($pending === null) {
            return;
        }
        // (Removed first: for an import from this class in a cycle of imports, the class has no aliases yet)
        unset(self::$pending_classes[$key]);
        [$class_fqsen, $context, $doc_comment, $ast_flags] = $pending;
        // As when the class is parsed: `self` in an alias is this class.
        $context = $context->withScope(new ClassScope($context->getScope(), $class_fqsen, $ast_flags));
        $code_base->setTypeAliasesOfClass($class_fqsen, []);
        $code_base->setTypeAliasesOfClass($class_fqsen, Builder::extractTypeAliases($code_base, $context, $doc_comment, $class_fqsen));
    }

    /**
     * Adds the classes that are declared in $stmts to self::$pending_classes.
     *
     * @param Node $stmts the statements of a file or of a `namespace { }` block
     * @param list<string> $keys the keys of the classes that were added
     */
    private static function collectClasses(CodeBase $code_base, Context $context, Node $stmts, array &$keys): void
    {
        foreach ($stmts->children as $stmt) {
            if (!($stmt instanceof Node)) {
                // `'@phan-type Id = int';` as a statement declares an alias for the code that follows.
                if (\is_string($stmt) && \str_contains($stmt, '@phan-type')
                    && \preg_match_all(Builder::PHAN_TYPE_ALIAS_REGEX, $stmt, $matches, \PREG_SET_ORDER) > 0
                ) {
                    foreach ($matches as $group) {
                        Builder::addTypeAliasMapping($code_base, $context, $group[1], $group[2]);
                    }
                }
                continue;
            }
            switch ($stmt->kind) {
                case ast\AST_NAMESPACE:
                    // (Not ParseVisitor::visitNamespace(), which records the namespace in the CodeBase)
                    $context = $context->withNamespace('\\' . (string)$stmt->children['name']);
                    $inner_stmts = $stmt->children['stmts'];
                    if ($inner_stmts instanceof Node) {
                        // `namespace NS { ... }`. The context is kept for what follows, as when the file is parsed:
                        // the next block then gets type aliases of its own.
                        self::collectClasses($code_base, $context, $inner_stmts, $keys);
                    }
                    break;
                case ast\AST_IF:
                case ast\AST_IF_ELEM:
                case ast\AST_STMT_LIST:
                case ast\AST_DECLARE:
                    // e.g. `if (!class_exists('C')) { class C {} }`, `declare(strict_types=1) { ... }`
                    foreach ($stmt->children as $child) {
                        if ($child instanceof Node && \in_array($child->kind, [ast\AST_IF_ELEM, ast\AST_STMT_LIST], true)) {
                            self::collectClasses($code_base, $context, $child->kind === ast\AST_STMT_LIST ? $child : new Node(ast\AST_STMT_LIST, 0, [$child], $child->lineno), $keys);
                        }
                    }
                    break;
                case ast\AST_USE:
                    $context = (new ParseVisitor($code_base, $context))->visitUse($stmt);
                    break;
                case ast\AST_GROUP_USE:
                    $context = (new ParseVisitor($code_base, $context))->visitGroupUse($stmt);
                    break;
                case ast\AST_CLASS:
                    $name = $stmt->children['name'];
                    if (!\is_string($name) || ($stmt->flags & ast\flags\CLASS_ANONYMOUS)) {
                        break;
                    }
                    try {
                        $class_fqsen = FullyQualifiedClassName::fromStringInContext($name, $context);
                    } catch (FQSENException) {
                        break;
                    }
                    $key = \strtolower($class_fqsen->__toString());
                    if ($code_base->hasTypeAliasesOfClass($class_fqsen) || isset(self::$pending_classes[$key])) {
                        break;
                    }
                    $doc_comment = $stmt->children['docComment'] ?? null;
                    self::$pending_classes[$key] = [$class_fqsen, $context->withLineNumberStart($stmt->lineno), \is_string($doc_comment) ? $doc_comment : '', $stmt->flags];
                    $keys[] = $key;
                    break;
            }
        }
    }
}
