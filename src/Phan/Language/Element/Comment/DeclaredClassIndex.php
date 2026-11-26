<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\UnionType;
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
 * that is neither parsed yet nor internal. A declaration that is found in a comment or a string
 * makes a class exist that does not: the annotation is then used as before, and the class is reported as undeclared.
 *
 * @internal
 */
final class DeclaredClassIndex
{
    /** @var ?array<string,true> the lower-case names (without the leading backslash) of the classes that the files declare */
    private static $declared_classes = null;

    /** @var list<string> the file list that $declared_classes was built for */
    private static $indexed_file_path_list = [];

    /**
     * @return bool whether a class that $union_type names is not declared: not parsed so far, not internal, and not found in the files
     */
    public static function hasUndeclaredClass(CodeBase $code_base, UnionType $union_type): bool
    {
        // Every class of a type is printed with a leading backslash, wherever it is:
        // in a generic argument, an array shape or a closure type.
        // (A template type, `static` and `self` are not)
        if (\preg_match_all('/\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*/', (string)$union_type, $matches) === 0) {
            return false;
        }
        foreach (\array_unique($matches[0]) as $name) {
            if (!self::isDeclared($code_base, $name)) {
                return true;
            }
        }
        return false;
    }

    private static function isDeclared(CodeBase $code_base, string $name): bool
    {
        try {
            if ($code_base->hasClassWithFQSEN(FullyQualifiedClassName::fromFullyQualifiedString($name))) {
                return true;
            }
        } catch (Throwable) {
            // (Not a valid class name)
            return false;
        }
        $file_path_list = $code_base->getFilePathListForTypeAliases();
        if (self::$declared_classes === null || self::$indexed_file_path_list !== $file_path_list) {
            self::$declared_classes = self::buildIndex($file_path_list);
            self::$indexed_file_path_list = $file_path_list;
        }
        return isset(self::$declared_classes[\strtolower(\ltrim($name, '\\'))]);
    }

    /**
     * @param list<string> $file_path_list
     * @return array<string,true>
     */
    private static function buildIndex(array $file_path_list): array
    {
        $declared_classes = [];
        foreach ($file_path_list as $file_path) {
            $contents = @\file_get_contents(Config::projectPath($file_path));
            if (!\is_string($contents) || $contents === '') {
                continue;
            }
            // A namespace declaration, or the declaration of a class, interface, trait or enum.
            // (`enum` is followed by a name, and not by `(`, `=` or `;`, which is what a function or constant with that name has)
            if (!\preg_match_all(
                '/^[ \t]*(?:<\?php[ \t]+)?(?:namespace[ \t]+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*)[ \t]*[;{]|namespace[ \t]*(\{)|(?:(?:abstract|final|readonly)[ \t]+)*(?:class|interface|trait|enum)[ \t]+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))/mi',
                $contents,
                $matches,
                \PREG_SET_ORDER
            )) {
                continue;
            }
            $namespace = '';
            foreach ($matches as $match) {
                if (($match[3] ?? '') !== '') {
                    $declared_classes[\strtolower($namespace . $match[3])] = true;
                } elseif (($match[2] ?? '') !== '') {
                    $namespace = '';
                } else {
                    $namespace = $match[1] . '\\';
                }
            }
        }
        return $declared_classes;
    }
}
