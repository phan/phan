<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\Config;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Type;
use Phan\Library\FileCache;
use Phan\Library\FileCacheEntry;
use PhpToken;

/**
 * Finds the class names in the types of the annotations of all comments of a file.
 *
 * php-ast only has the doc comments of declarations. The type of a comment of a statement
 * (`/** (at)var Foo $x *\/` above an assignment or in a loop) is not seen by the analysis,
 * so a `use` statement that only such a comment refers to looked unused.
 *
 * This is only read for a file with a `use` statement that nothing else refers to.
 */
final class CommentClassReferences
{
    /**
     * A name that is not part of a longer name, of a fully qualified name or of a variable: for `Foo\Bar`, `Foo\`.
     * Not the name of a class constant (`BAR` of `Foo::BAR`), and not a part of a type that is written with hyphens (`positive-int`).
     */
    private const FIRST_NAME_PART_REGEX = '/(?<![\\\\\w$\x80-\xff-]|::)[a-zA-Z_\x80-\xff][\w\x80-\xff]*+(?!-)\\\\?/';

    /**
     * What is not a class name in a type: a string literal, and the key of a field of an array shape
     * (`'Foo'`, and `Foo:` of `array{Foo: int, Bar?: int}`, also `Foo.Bar:`. Not `A :` of `($x is int ? A : B)`, and not `Foo::` of a class constant).
     */
    private const NOT_A_CLASS_NAME_REGEX =
        '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|(?<=[{,])\s*' . Type::shape_key_regex . '\s*:(?!:)/';

    /** @var ?FileCacheEntry the file that $names_of_namespaces is for */
    private static $entry = null;

    /** @var array<int,array<string,int>> see namesByNamespace() */
    private static $names_of_namespaces = [];

    /**
     * @param string $file the path of the file, as in the context
     * @param int $namespace_id the number of the namespace declaration in the file, as in the context: 1 for the first one,
     * 0 for a file without one. (A namespace can be declared more than once in a file, and each declaration has its own `use` statements)
     * @return array<string,int> the names, in lower case (with the line of the last comment that has the name), that the types of `(at)var`, `(at)param`, `(at)return` and `(at)throws`
     * annotations (also phan-, phpstan- and psalm-) of the comments after this namespace declaration (and before the next one) begin with.
     * Not the doc comments of declarations, which are part of the AST.
     * `foo` for `Foo`, `Foo[]`, `Foo\Bar` and `array<int,Foo>`, nothing for `\Foo`.
     * (Also the other words of such a type, e.g. `array` and `int`)
     */
    public static function namesInNamespace(string $file, int $namespace_id): array
    {
        try {
            $entry = FileCache::getOrReadEntry(Config::projectPath($file));
        } catch (\Throwable) {
            return [];
        }
        // (The daemon and the language server have a new entry for new contents of a file)
        if (self::$entry !== $entry) {
            self::$entry = $entry;
            self::$names_of_namespaces = self::namesByNamespace($entry);
        }
        return self::$names_of_namespaces[$namespace_id] ?? [];
    }

    /**
     * @return array<int,array<string,int>> for each namespace declaration of the file (see namesInNamespace()),
     * the names that namesInNamespace() returns.
     */
    private static function namesByNamespace(FileCacheEntry $entry): array
    {
        if (!\str_contains($entry->getContents(), '@')) {
            return [];
        }
        try {
            $tokens = PhpToken::tokenize($entry->getContents());
        } catch (\Throwable) {
            return [];
        }
        $declaration_ids = self::declarationTokenIds();
        $result = [];
        $namespace_id = 0;
        $is_after_namespace_keyword = false;
        // A doc comment, until the token after it shows whether it is the doc comment of a declaration
        $doc_comment = null;
        $doc_comment_line = 0;
        $is_after_static_keyword = false;
        foreach ($tokens as $token) {
            $id = $token->id;
            if ($id === \T_WHITESPACE) {
                continue;
            }
            if ($is_after_static_keyword && $id !== \T_COMMENT && $id !== \T_DOC_COMMENT) {
                // `static $x = 1;` and `static::f();` are statements, `static function` and `static int $x` are declarations.
                $is_after_static_keyword = false;
                if ($doc_comment !== null && ($id === \T_VARIABLE || $id === \T_DOUBLE_COLON)) {
                    self::addNamesOfComment($doc_comment, $doc_comment_line, $result[$namespace_id]);
                }
                $doc_comment = null;
            }
            if ($id === \T_DOC_COMMENT || $id === \T_COMMENT) {
                $text = $token->text;
                // (`/* (at)var Foo $x */` is read by editors, and by Phan in a string. Not `// ...` and `# ...`)
                if ($id === \T_COMMENT) {
                    if (\str_starts_with($text, '/*') && \str_contains($text, '@')) {
                        self::addNamesOfComment($text, $token->line, $result[$namespace_id]);
                    }
                    continue;
                }
                if ($is_after_static_keyword) {
                    continue;
                }
                if ($doc_comment !== null) {
                    // Only the last doc comment before a declaration is its doc comment.
                    self::addNamesOfComment($doc_comment, $doc_comment_line, $result[$namespace_id]);
                }
                $doc_comment = $text;
                $doc_comment_line = $token->line;
                continue;
            }
            if ($doc_comment !== null) {
                if ($id === \T_STATIC) {
                    $is_after_static_keyword = true;
                    continue;
                }
                // The doc comment of a declaration is part of the AST: its types are already read,
                // with the template types and type aliases that are not class names.
                if (!isset($declaration_ids[$id])) {
                    self::addNamesOfComment($doc_comment, $doc_comment_line, $result[$namespace_id]);
                }
                $doc_comment = null;
            }
            if ($is_after_namespace_keyword) {
                // `namespace NS\Sub;`, `namespace NS {`, or `namespace {` for the global namespace.
                // (`namespace\foo()` is a single token. Not `function namespace()`, `const NAMESPACE = 1` and `Foo::NAMESPACE`)
                $is_after_namespace_keyword = false;
                if ($id === \T_NAME_QUALIFIED || $id === \T_STRING || $token->text === '{') {
                    $namespace_id++;
                }
                continue;
            }
            if ($id === \T_NAMESPACE) {
                $is_after_namespace_keyword = true;
            }
        }
        if ($doc_comment !== null) {
            self::addNamesOfComment($doc_comment, $doc_comment_line, $result[$namespace_id]);
        }
        return $result;
    }

    /**
     * @return array<int,true> the ids of the tokens that a declaration begins with. A doc comment before one is the doc comment of that declaration.
     */
    private static function declarationTokenIds(): array
    {
        $ids = [
            \T_CLASS => true, \T_INTERFACE => true, \T_TRAIT => true, \T_ENUM => true, \T_FUNCTION => true, \T_FN => true,
            \T_CONST => true, \T_CASE => true, \T_ABSTRACT => true, \T_FINAL => true, \T_READONLY => true, \T_VAR => true,
            \T_PUBLIC => true, \T_PROTECTED => true, \T_PRIVATE => true, \T_ATTRIBUTE => true,
        ];
        // `private(set)` etc. of PHP 8.4
        foreach (['T_PUBLIC_SET', 'T_PROTECTED_SET', 'T_PRIVATE_SET'] as $name) {
            if (\defined($name)) {
                $ids[(int)\constant($name)] = true;
            }
        }
        return $ids;
    }

    /**
     * @param int $line the line that the comment starts on
     * @param ?array<string,int> $names the names of the namespace so far @phan-output-reference
     */
    private static function addNamesOfComment(string $comment, int $line, ?array &$names): void
    {
        if (!\str_contains($comment, '@')) {
            return;
        }
        // (A type can be written on several lines: `(at)var array{` and then a line for each field)
        $comment = Builder::withMultilineTypesJoined($comment);
        foreach ([Builder::PARAM_COMMENT_REGEX, Builder::RETURN_OR_THROWS_COMMENT_REGEX] as $regex) {
            if (!\preg_match_all($regex, $comment, $matches)) {
                continue;
            }
            foreach ($matches[2] as $type) {
                if ($type === '') {
                    continue;
                }
                $type = \preg_replace(self::NOT_A_CLASS_NAME_REGEX, ' ', $type) ?? $type;
                if (!\preg_match_all(self::FIRST_NAME_PART_REGEX, $type, $name_matches)) {
                    continue;
                }
                foreach ($name_matches[0] as $name) {
                    if (\str_ends_with($name, '\\')) {
                        $name = \substr($name, 0, -1);
                    } elseif ($name === 'number' || $name === 'numeric') {
                        // (`number` and `numeric` in lower case are types of PHPStan and Psalm, also when a class is imported as `Number`.
                        // Not as the first part of a name: `number\Foo`)
                        continue;
                    }
                    // (Comments are read in the order of the file: this is the last line so far)
                    $names[\strtolower($name)] = $line;
                }
            }
        }
    }
}
