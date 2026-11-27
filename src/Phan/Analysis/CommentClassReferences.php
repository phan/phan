<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\Config;
use Phan\Language\Element\Comment\Builder;
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
    /** A name that is not part of a longer name, of a fully qualified name or of a variable: for `Foo\Bar`, `Foo`. */
    private const FIRST_NAME_PART_REGEX = '/(?<![\\\\\w$\x80-\xff])[a-zA-Z_\x80-\xff][\w\x80-\xff]*/';

    /** @var ?FileCacheEntry the file that $names_of_namespaces is for */
    private static $entry = null;

    /** @var array<string,array<string,true>> see namesByNamespace() */
    private static $names_of_namespaces = [];

    /**
     * @param string $file the path of the file, as in the context
     * @param string $namespace the namespace, as in the context (`\NS\Sub`, or `\` for the global namespace)
     * @return array<string,true> the names, in lower case, that the types of `(at)var`, `(at)param`, `(at)return` and `(at)throws`
     * annotations (also phan-, phpstan- and psalm-) of the comments in this namespace of the file begin with:
     * `foo` for `Foo`, `Foo[]`, `Foo\Bar` and `array<int,Foo>`, nothing for `\Foo`.
     * (Also the other words of such a type, e.g. `array` and `int`)
     */
    public static function namesInNamespace(string $file, string $namespace): array
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
        return self::$names_of_namespaces[\strtolower($namespace)] ?? [];
    }

    /**
     * @return array<string,array<string,true>> for each namespace of the file (in lower case, `\ns\sub`),
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
        $result = [];
        $namespace = '\\';
        $is_after_namespace_keyword = false;
        foreach ($tokens as $token) {
            $id = $token->id;
            if ($id === \T_WHITESPACE) {
                continue;
            }
            if ($id === \T_DOC_COMMENT || $id === \T_COMMENT) {
                $text = $token->text;
                // (`/* (at)var Foo $x */` is read by editors, and by Phan in a string. Not `// ...` and `# ...`)
                if ($text[1] === '*' && \str_contains($text, '@')) {
                    self::addNamesOfComment($text, $result[$namespace]);
                }
                continue;
            }
            if ($is_after_namespace_keyword) {
                // `namespace NS\Sub;`, `namespace NS {`, or `namespace {` for the global namespace.
                // (`namespace\foo()` is a single token)
                $is_after_namespace_keyword = false;
                $namespace = $id === \T_NAME_QUALIFIED || $id === \T_STRING ? '\\' . \strtolower($token->text) : '\\';
                continue;
            }
            if ($id === \T_NAMESPACE) {
                $is_after_namespace_keyword = true;
            }
        }
        return $result;
    }

    /**
     * @param ?array<string,true> $names the names of the namespace so far @phan-output-reference
     */
    private static function addNamesOfComment(string $comment, ?array &$names): void
    {
        foreach ([Builder::PARAM_COMMENT_REGEX, Builder::RETURN_OR_THROWS_COMMENT_REGEX] as $regex) {
            if (!\preg_match_all($regex, $comment, $matches)) {
                continue;
            }
            foreach ($matches[2] as $type) {
                if ($type === '' || !\preg_match_all(self::FIRST_NAME_PART_REGEX, $type, $name_matches)) {
                    continue;
                }
                foreach ($name_matches[0] as $name) {
                    $names[\strtolower($name)] = true;
                }
            }
        }
    }
}
