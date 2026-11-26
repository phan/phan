<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * Tells whether the classes that a type names are declared somewhere in the files that Phan parses,
 * while those files are still being parsed.
 *
 * Used for `(at)phpstan-` annotations: a name that Phan does not know as a type alias
 * (e.g. a global alias of `typeAliases` in phpstan.neon) is read as a class,
 * and such an annotation should not replace the plain one.
 *
 * The files are searched for declarations with a regular expression, once, when the first class is looked up
 * that is neither parsed yet nor internal. The search prefers finding too much: `class Foo` in a comment or a string
 * makes a class exist that does not, and the annotation is then used as before, and the class is reported as undeclared.
 * (A class that is not found makes Phan ignore an annotation without saying so.)
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
     * This looks at the classes anywhere in the type: in generic arguments, array shapes and closure types.
     */
    public static function hasUndeclaredClass(CodeBase $code_base, UnionType $union_type): bool
    {
        foreach ($union_type->getTypeSet() as $type) {
            if (self::typeHasUndeclaredClass($code_base, $type)) {
                return true;
            }
        }
        return false;
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

    private static function typeHasUndeclaredClass(CodeBase $code_base, Type $type): bool
    {
        // The classes of this type, and of the elements, fields, parameters and return type of an array or closure type
        foreach ($type->getReferencedClasses() as $class_type) {
            if ($class_type instanceof TemplateType || $class_type instanceof StaticOrSelfType) {
                continue;
            }
            if ($class_type->isObjectWithKnownFQSEN() && !self::isDeclared($code_base, FullyQualifiedClassName::fromType($class_type))) {
                return true;
            }
            if ($class_type !== $type && self::templateParametersHaveUndeclaredClass($code_base, $class_type)) {
                return true;
            }
        }
        // `Box<GlobalAlias>`, `class-string<GlobalAlias>`
        return self::templateParametersHaveUndeclaredClass($code_base, $type);
    }

    private static function templateParametersHaveUndeclaredClass(CodeBase $code_base, Type $type): bool
    {
        foreach ($type->getTemplateParameterTypeList() as $template_parameter_type) {
            if (self::hasUndeclaredClass($code_base, $template_parameter_type)) {
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
        $name = (string)$class_fqsen;
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
            $contents = self::$file_contents_overrides[$file_path] ?? @\file_get_contents(Config::projectPath($file_path));
            if (!\is_string($contents) || $contents === '') {
                continue;
            }
            // A namespace declaration, or the declaration of a class, interface, trait or enum, wherever it is on a line
            // (`#[Attribute] class Foo`, `<?php namespace NS; class Foo {}`).
            // Not `Foo::class`, `$class`, `->class`, and not `new class extends Foo`.
            // This also finds `class Foo` in a comment or a string, and then Foo is taken to be declared.
            if (!\preg_match_all(
                '/(?<![a-zA-Z0-9_\x7f-\xff$>:\\\\])(?:namespace\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\\\\]*)\s*[;{]|namespace\s*(\{)|(?:class|interface|trait|enum)\s+(?!(?:extends|implements)\b)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))/i',
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
