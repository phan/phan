<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\IntersectionType;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use PhpToken;
use Throwable;

/**
 * Tells whether the classes that a type names are declared somewhere in the files that Phan parses,
 * while those files are still being parsed.
 *
 * Used for `(at)phpstan-` annotations: a name that Phan does not know as a type alias
 * (e.g. a global alias of `typeAliases` in phpstan.neon) is read as a class,
 * and such an annotation should not replace the plain one.
 *
 * The files are searched for declarations with a regular expression, once, when the first class is looked up
 * that is neither parsed yet nor internal.
 *
 * A class that is not found makes Phan ignore an annotation without saying so, so the search prefers finding too much:
 * `class Foo` in a comment or a string declares Foo. The annotation is then used as it was before,
 * and the class is reported as undeclared.
 *
 * The namespace of the declarations is exact. A file with one namespace declaration that is a statement of its own
 * (nearly every file) is searched with regular expressions only; any other file with the word `namespace` is tokenized.
 *
 * @internal
 */
final class DeclaredClassIndex
{
    /** @var ?array<string,true> the lower-case names (without the leading backslash) of the classes that the files declare */
    private static $declared_classes = null;

    /** @var list<string> the file list that $declared_classes was built for */
    private static $indexed_file_path_list = [];

    /** @var array<string,string> maps relative file paths to the contents to search instead of the contents on disk */
    private static $file_contents_overrides = [];

    /**
     * Forgets the declarations that were found, and sets the contents to search instead of the contents on disk
     * (the daemon and the language server analyze files that are not saved yet, and files change between requests).
     * @param array<string,string> $file_contents_overrides maps relative file paths to contents
     */
    public static function reset(array $file_contents_overrides = []): void
    {
        self::$file_contents_overrides = $file_contents_overrides;
        self::$declared_classes = null;
    }

    /**
     * @return bool whether a class that $union_type names is not declared: not parsed so far, not internal, and not found in the files.
     * This looks at the classes anywhere in the type: in generic arguments (`list<class-string<Foo>>`), array shapes and closure types.
     */
    public static function hasUndeclaredClass(CodeBase $code_base, UnionType $union_type): bool
    {
        foreach ($union_type->getTypesRecursively() as $type) {
            if (self::typeHasUndeclaredClass($code_base, $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Type $type a type that UnionType::getTypesRecursively() yields
     */
    private static function typeHasUndeclaredClass(CodeBase $code_base, Type $type): bool
    {
        if ($type instanceof IntersectionType) {
            // (Of an intersection type, getTypesRecursively() yields the parts but not what the parts are made of: `Known&Box<Foo>`)
            foreach ($type->getTypeParts() as $part) {
                foreach ($part->getTypesRecursively() as $inner_type) {
                    if (self::typeHasUndeclaredClass($code_base, $inner_type)) {
                        return true;
                    }
                }
            }
            return false;
        }
        // (A closure or callable type is not a class; its parameters and return type are yielded as well)
        if ($type instanceof TemplateType || $type instanceof StaticOrSelfType || $type instanceof FunctionLikeDeclarationType || !$type->isObjectWithKnownFQSEN()) {
            return false;
        }
        return !self::isDeclared($code_base, FullyQualifiedClassName::fromType($type));
    }

    /**
     * @return bool whether a condition or a branch of $conditional names a class that is not declared
     */
    public static function conditionalHasUndeclaredClass(CodeBase $code_base, ConditionalReturnType $conditional): bool
    {
        if (self::hasUndeclaredClass($code_base, $conditional->getCondition())) {
            return true;
        }
        foreach ([$conditional->getIfTrue(), $conditional->getIfFalse()] as $branch) {
            if ($branch instanceof ConditionalReturnType ? self::conditionalHasUndeclaredClass($code_base, $branch) : self::hasUndeclaredClass($code_base, $branch)) {
                return true;
            }
        }
        return false;
    }

    private static function isDeclared(CodeBase $code_base, FullyQualifiedClassName $class_fqsen): bool
    {
        if ($code_base->hasClassWithFQSEN($class_fqsen)) {
            return true;
        }
        $file_path_list = $code_base->getFilePathListForTypeAliases();
        if (self::$declared_classes === null || self::$indexed_file_path_list !== $file_path_list) {
            self::$declared_classes = self::buildIndex($file_path_list);
            self::$indexed_file_path_list = $file_path_list;
        }
        return isset(self::$declared_classes[\strtolower(\ltrim((string)$class_fqsen, '\\'))]);
    }

    private const NAME_REGEX = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';

    /** `namespace Foo\Bar;`, `namespace Foo {` and `namespace {`, not the operator `namespace\foo()` */
    private const NAMESPACE_REGEX = '/(?<![a-zA-Z0-9_\x7f-\xff$>:\\\\])namespace(?:\s+(' . self::NAME_REGEX . '(?:\\\\' . self::NAME_REGEX . ')*)\s*[;{]|\s*\{)/i';

    /**
     * The declaration of a class, interface, trait or enum, wherever it is on a line
     * (`#[Attribute] class Foo`, `<?php namespace NS; class Foo {}`), also with a block comment before the name.
     * Not `Foo::class`, `$class`, `->class`, and not `new class extends Foo`.
     */
    private const DECLARATION_REGEX = '/(?<![a-zA-Z0-9_\x7f-\xff$>:\\\\])(?:class|interface|trait|enum)(?>\s+|\/\*[^*]*+(?:\*(?!\/)[^*]*+)*+\*\/)++(?!(?:extends|implements|class|interface|trait|enum)\b)(' . self::NAME_REGEX . ')/i';

    /**
     * @param list<string> $file_path_list
     * @return array<string,true>
     */
    private static function buildIndex(array $file_path_list): array
    {
        $declared_classes = [];
        foreach ($file_path_list as $file_path) {
            $contents = self::$file_contents_overrides[$file_path] ?? @\file_get_contents(Config::projectPath($file_path));
            if (!\is_string($contents) || $contents === '') {
                continue;
            }
            $namespace = self::namespaceOfFile($contents);
            $count = $namespace !== null ? \preg_match_all(self::DECLARATION_REGEX, $contents, $matches) : false;
            if ($count === false) {
                // More than one namespace, or text that looks like a namespace declaration: the tokens say which is which.
                // (Also if the regular expression failed, e.g. at the backtrack limit)
                self::addDeclarationsFromTokens($contents, $declared_classes);
                continue;
            }
            foreach ($matches[1] ?? [] as $name) {
                $declared_classes[\strtolower($namespace . $name)] = true;
            }
        }
        return $declared_classes;
    }

    /**
     * @return ?string the namespace of all declarations of a file ('' or `Foo\Bar\`),
     * or null if the regular expression cannot tell: the file has to be tokenized.
     */
    private static function namespaceOfFile(string $contents): ?string
    {
        if (\stripos($contents, 'namespace') === false) {
            return '';
        }
        // The word `namespace` where it can be the keyword of a declaration: not `$namespace`, `->namespace`, `'namespace'`,
        // and not the operator `namespace\foo()`.
        $word_count = \preg_match_all('/(?<![a-zA-Z0-9_\x7f-\xff$>:\\\\\'"])namespace(?![a-zA-Z0-9_\x7f-\xff]|\s*\\\\)/i', $contents);
        if ($word_count === 0) {
            return '';
        }
        // (With another such word, e.g. in a comment or as `namespace /* comment */ Foo;`, the tokens say what is declared)
        $count = $word_count === 1 ? \preg_match_all(self::NAMESPACE_REGEX, $contents, $matches, \PREG_OFFSET_CAPTURE) : false;
        if ($count !== 1 || !isset($matches[1][0]) || !\is_array($matches[1][0]) || $matches[1][0][1] < 0) {
            return null;
        }
        $offset = $matches[0][0][1];
        // The one candidate has to be a statement of its own: at the start of a line (or after `<?php`),
        // and not in a block comment (`namespace Demo;` in a comment would move the declarations to another namespace).
        $line_start = \strrpos($contents, "\n", $offset - \strlen($contents));
        $line_prefix = \substr($contents, $line_start === false ? 0 : $line_start + 1, $offset - ($line_start === false ? 0 : $line_start + 1));
        if (!\preg_match('/^\s*(?:<\?php\s+)?$/iD', $line_prefix)) {
            return null;
        }
        $comment_start = \strrpos($contents, '/*', $offset - \strlen($contents));
        if ($comment_start !== false) {
            $comment_end = \strpos($contents, '*/', $comment_start + 2);
            if ($comment_end === false || $comment_end > $offset) {
                return null;
            }
        }
        return $matches[1][0][0] . '\\';
    }

    /**
     * @param array<string,true> $declared_classes
     */
    private static function addDeclarationsFromTokens(string $contents, array &$declared_classes): void
    {
        try {
            $tokens = PhpToken::tokenize($contents);
        } catch (Throwable) {
            return;
        }
        $namespace = '';
        $count = \count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $id = $tokens[$i]->id;
            if ($id !== \T_NAMESPACE && $id !== \T_CLASS && $id !== \T_INTERFACE && $id !== \T_TRAIT && $id !== \T_ENUM) {
                continue;
            }
            $j = $i + 1;
            while ($j < $count && $tokens[$j]->isIgnorable()) {
                $j++;
            }
            if ($j >= $count) {
                break;
            }
            $next = $tokens[$j];
            if ($id === \T_NAMESPACE) {
                if ($next->id === \T_NAME_QUALIFIED || $next->id === \T_STRING) {
                    $namespace = $next->text . '\\';
                } elseif ($next->text === '{') {
                    $namespace = '';
                }
            } elseif ($next->id === \T_STRING) {
                $declared_classes[\strtolower($namespace . $next->text)] = true;
            }
        }
    }
}
