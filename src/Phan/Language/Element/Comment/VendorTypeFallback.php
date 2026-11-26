<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\CodeBase;
use Phan\Language\Element\Comment;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type\StaticOrSelfType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use WeakMap;

/**
 * Restores the type of `(at)param`, `(at)var` or `(at)return` when the `(at)phpstan-` annotation that replaced it
 * names a class that does not exist.
 *
 * Such a name is usually a type alias that Phan does not know, e.g. a global alias of `typeAliases` in phpstan.neon.
 * Phan reads the name as a class, and can only tell that the class does not exist once all files were parsed.
 * The annotation was written for another tool, so nothing is reported for it.
 *
 * Only the comments that have such an annotation are recorded: this costs nothing for the other elements.
 *
 * @internal
 */
final class VendorTypeFallback
{
    /**
     * @var ?WeakMap<Comment,array{param?:array<string,UnionType>,var?:array<string,UnionType>,return?:UnionType}>
     * the types of the plain annotations that `(at)phpstan-` annotations of a comment replaced (an empty type if there was none)
     */
    private static $types_of_comment = null;

    /** @var ?WeakMap<FunctionInterface|Property,UnionType|true> the elements to check: the plain type of a property, true for a function */
    private static $elements = null;

    /**
     * Records the types of the plain annotations that `(at)phpstan-` annotations of $comment replaced.
     * @param array{param?:array<string,UnionType>,var?:array<string,UnionType>,return?:UnionType} $plain_types
     */
    public static function record(Comment $comment, array $plain_types): void
    {
        self::$types_of_comment ??= new WeakMap();
        self::$types_of_comment[$comment] = $plain_types;
    }

    /**
     * Called when a function, method or closure gets its doc comment.
     */
    public static function registerFunction(FunctionInterface $function, Comment $comment): void
    {
        if (self::$types_of_comment !== null && isset(self::$types_of_comment[$comment])) {
            self::$elements ??= new WeakMap();
            self::$elements[$function] = true;
        }
    }

    /**
     * Called when a property is declared.
     * @param string $annotation_name the name that the `(at)var` annotation of this property has in $comment (may be '')
     */
    public static function registerProperty(Property $property, Comment $comment, string $annotation_name): void
    {
        if (self::$types_of_comment === null) {
            return;
        }
        $plain_type = self::$types_of_comment[$comment]['var'][$annotation_name] ?? null;
        if ($plain_type !== null) {
            self::$elements ??= new WeakMap();
            self::$elements[$property] = $plain_type;
        }
    }

    /**
     * Restores the plain types of the recorded elements that have a class that does not exist.
     * Called once the files were parsed, before the analysis uses the types.
     */
    public static function resolveAll(CodeBase $code_base): void
    {
        $elements = self::$elements;
        if ($elements === null || \count($elements) === 0) {
            return;
        }
        self::$elements = null;
        foreach ($elements as $element => $plain_type) {
            if ($element instanceof Property) {
                if ($plain_type instanceof UnionType && self::hasUndeclaredClass($code_base, $element->getUnionType())) {
                    $element->setUnionType(self::plainTypeWithRealTypes($plain_type, $element->getUnionType()));
                    $element->setPHPDocUnionType($plain_type);
                    $element->replacePendingDefaultTypeCheckType($plain_type);
                }
            } elseif ($element instanceof FunctionInterface) {
                self::resolveFunction($code_base, $element);
            }
        }
    }

    private static function resolveFunction(CodeBase $code_base, FunctionInterface $function): void
    {
        $comment = $function->getComment();
        if ($comment === null || self::$types_of_comment === null) {
            return;
        }
        $plain_types = self::$types_of_comment[$comment] ?? null;
        if ($plain_types === null) {
            return;
        }
        // The comment is where the types of the parameters are taken from when the scope of the function is set up,
        // and what the checks of the doc comment against the signature look at.
        foreach ($plain_types['param'] ?? [] as $name => $plain_type) {
            $comment_parameter = $comment->getParameterMap()[$name] ?? null;
            if ($comment_parameter && self::hasUndeclaredClass($code_base, $comment_parameter->getUnionType())) {
                $comment->restorePlainTypes($name, $plain_type, null);
                self::restoreParameterType($function, $name, $plain_type);
            }
        }
        $plain_return_type = $plain_types['return'] ?? null;
        if ($plain_return_type !== null && $comment->hasReturnUnionType() && self::hasUndeclaredClass($code_base, $comment->getReturnType())) {
            $comment->restorePlainTypes(null, null, $plain_return_type);
            $function->setUnionType(self::plainTypeWithRealTypes($plain_return_type, $function->getUnionType()));
            $function->setPHPDocReturnType($plain_return_type->isEmpty() ? null : $plain_return_type);
        }
    }

    /**
     * (The parameter has the type of the comment if the function was set up with it before the files were all parsed)
     */
    private static function restoreParameterType(FunctionInterface $function, string $name, UnionType $plain_type): void
    {
        foreach ($function->getParameterList() as $parameter) {
            if ($parameter->getName() === $name) {
                $parameter->setUnionType(self::plainTypeWithRealTypes($plain_type, $parameter->getUnionType()));
            }
        }
        $phpdoc_parameter_type_map = $function->getPHPDocParameterTypeMap();
        if (isset($phpdoc_parameter_type_map[$name])) {
            if ($plain_type->isEmpty()) {
                unset($phpdoc_parameter_type_map[$name]);
            } else {
                $phpdoc_parameter_type_map[$name] = $plain_type;
            }
            $function->setPHPDocParameterTypeMap($phpdoc_parameter_type_map);
        }
    }

    /**
     * @return UnionType the plain phpdoc type (or the real type, if there was no plain annotation) with the real types of $current_type
     */
    private static function plainTypeWithRealTypes(UnionType $plain_type, UnionType $current_type): UnionType
    {
        $real_type_set = $current_type->getRealTypeSet();
        return UnionType::of($plain_type->getTypeSet() ?: $real_type_set, $real_type_set);
    }

    private static function hasUndeclaredClass(CodeBase $code_base, UnionType $union_type): bool
    {
        foreach ($union_type->getReferencedClasses() as $type) {
            if ($type instanceof TemplateType || $type instanceof StaticOrSelfType) {
                continue;
            }
            if (!$code_base->hasClassWithFQSEN(FullyQualifiedClassName::fromType($type))) {
                return true;
            }
        }
        return false;
    }
}
