<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents a template type that has not yet been resolved.
 * @see https://github.com/phan/phan/wiki/Generic-Types
 * @phan-pure
 */
final class TemplateType extends Type
{
    /** @var string an identifier for the template type. */
    private $template_type_identifier;

    /**
     * @param string $template_type_identifier
     * An identifier for the template type
     */
    protected function __construct(
        string $template_type_identifier,
        bool $is_nullable
    ) {
        $this->template_type_identifier = $template_type_identifier;
        $this->is_nullable = $is_nullable;
    }

    /**
     * Create an instance for this ID
     */
    public static function instanceForId(string $id, bool $is_nullable): TemplateType
    {
        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$id] ?? ($nullable_cache[$id] = new self($id, true));
        }
        static $cache = [];
        return $cache[$id] ?? ($cache[$id] = new self($id, false));
    }

    /**
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return self::instanceForId(
            $this->template_type_identifier,
            $is_nullable
        );
    }

    /**
     * @return string
     * The name associated with this type
     */
    public function getName(): string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * A string representation of this type in FQSEN form.
     * @override
     */
    public function asFQSENString(): string
    {
        return $this->template_type_identifier;
    }

    /**
     * @return string
     * The namespace associated with this type
     */
    public function getNamespace(): string
    {
        return '';
    }

    public function isObject(): bool
    {
        // Return true because we don't know, it may or may not be an object.
        // Not sure if this will be called.
        return false;
    }

    public function isObjectWithKnownFQSEN(): bool
    {
        // We have a template type ID, not an fqsen
        return false;
    }

    public function isPossiblyObject(): bool
    {
        return true;
    }

    /**
     * Returns true for `T` and `T[]` and `\MyClass<T>`, but not `\MyClass<\OtherClass>`
     *
     * Overridden in subclasses.
     */
    public function hasTemplateTypeRecursive(): bool
    {
        return true;
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     * A map from template type identifiers to concrete types
     *
     * @return UnionType
     * This UnionType with any template types contained herein
     * mapped to concrete types defined in the given map.
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ): UnionType {
        $type = $template_parameter_type_map[$this->template_type_identifier] ?? $this->asPHPDocUnionType();
        if ($this->is_nullable) {
            return $type->withIsNullable(true);
        }
        return $type;
    }

    /**
     * Combine two closures that generate union types
     * @param ?Closure(mixed, Context):UnionType $left
     * @param ?Closure(mixed, Context):UnionType $right
     * @return ?Closure(mixed, Context):UnionType
     */
    public static function combineParameterClosures(?Closure $left, ?Closure $right): ?Closure
    {
        if (!$left) {
            return $right;
        }
        if (!$right) {
            return $left;
        }

        /**
         * @param mixed $params
         */
        return static function ($params, Context $context) use ($left, $right): UnionType {
            return $left($params, $context)->withUnionType($right($params, $context));
        };
    }

    /**
     * @unused-param $code_base
     * @param TemplateType $template_type the template type that this union type is being searched for.
     *
     * @return ?Closure(UnionType, Context):UnionType a closure to map types to the template type wherever it was in the original union type
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type): ?Closure
    {
        // For `@param T $param`, if passed a union type such as `int`, then `T` must be `int|false`
        if ($this === $template_type) {
            return static function (UnionType $type, Context $_): UnionType {
                return $type;
            };
        }
        // For `@param ?T $param`, if passed `?int|false`, then `T` must be `int|false`
        if ($this->withIsNullable(false) === $template_type) {
            return static function (UnionType $type, Context $_): UnionType {
                return $type->withIsNullable(false);
            };
        }
        // Overridden in subclasses
        return null;
    }

    /**
     * @override
     */
    public function canUseInRealSignature(): bool
    {
        return false;
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     * @unused-param $other
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // Always possible until we support inferring `@template T as ConcreteType`
        return true;
    }

    /**
     * @param list<Type> $target_type_set
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function canCastToAnyTypeInSetWithoutConfig(array $target_type_set, CodeBase $code_base): bool
    {
        // Always possible until we support inferring `@template T as ConcreteType`
        return true;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isPossiblyNumeric(): bool
    {
        return true;
    }

    /**
     * Returns true if this could include the type `true`
     * (e.g. for `mixed`, `bool`, etc.)
     */
    public function isPossiblyTrue(): bool
    {
        return true;
    }

    /**
     * Returns true for types such as `mixed`, `bool`, `false`
     */
    public function isPossiblyFalse(): bool
    {
        return true;
    }

    public function isNullable(): bool
    {
        return true;
    }

    public function isNullableLabeled(): bool
    {
        return $this->is_nullable;
    }

    /**
     * @unused-param $other
     * @unused-param $code_base
     */
    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        return true;
    }
}
