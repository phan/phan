<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the return type `void`
 * @phan-pure
 */
final class VoidType extends NativeType implements LiteralTypeInterface
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'void';

    /**
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param list<UnionType> $template_parameter_type_list @phan-unused-param
     * A (possibly empty) list of template parameter types
     *
     * @param bool $is_nullable (@phan-unused-param)
     * True if this type can be null, false if it cannot
     * be null. (VoidType can always be null)
     */
    protected function __construct(
        string $namespace,
        string $name,
        $template_parameter_type_list,
        bool $is_nullable
    ) {
        parent::__construct(
            $namespace,
            $name,
            [],
            true
        );
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     * @override
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return $other->isNullable() || $other instanceof TemplateType;
    }

    /**
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        return $type->isNullable();
    }

    /**
     * void cannot be a subtype of a non-nullable type
     *
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return false;
    }

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     */
    public function isDefiniteNonObjectType(): bool
    {
        return true;
    }

    /**
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function canCastToTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        // Check to see if we have an exact object match
        if ($this === $type) {
            return true;
        }

        // Null(void) can cast to a nullable type or mixed (but not non-null-mixed).
        return $type->isNullable();
    }

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     * @unused-param $code_base
     */
    public function isDefiniteNonCallableType(CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly (accounting for templates, intersection types, etc)
     */
    public function canCastToType(Type $type, CodeBase $code_base): bool
    {
        // Check to see if we have an exact object match
        if ($this === $type) {
            return true;
        }

        // Null can cast to a nullable type.
        if ($type->is_nullable) {
            return true;
        }

        if (Config::get_null_casts_as_any_type()) {
            return true;
        }

        // NullType is a sub-type of ScalarType. So it's affected by scalar_implicit_cast.
        if ($type->isScalar()) {
            if (Config::getValue('scalar_implicit_cast')) {
                return true;
            }
            $scalar_implicit_partial = Config::getValue('scalar_implicit_partial');
            // check if $type->getName() is in the list of permitted types $this->getName() can cast to.
            if (\count($scalar_implicit_partial) > 0 &&
                \in_array($type->getName(), $scalar_implicit_partial['null'] ?? [], true)) {
                return true;
            }
        }
        if ($type instanceof MixedType) {
            return $type->isNullable();
        }

        // Test to see if we can cast to the non-nullable version
        // of the target type.
        return parent::canCastToNonNullableTypeHandlingTemplates($type, $code_base);
    }

    /**
     * @unused-param $type
     * @override
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // null_casts_as_any_type means that null or nullable can cast to any type?
        // But don't allow it for void?
        return false;
    }

    /**
     * @override
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return false;
    }

    /**
     * @unused-param $is_nullable
     * @override
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        return $this;
    }

    public function __toString(): string
    {
        return self::NAME;
    }

    public function isNullable(): bool
    {
        return true;
    }

    public function isNullableLabeled(): bool
    {
        return true;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;  // Null is always falsey.
    }

    public function isPossiblyTruthy(): bool
    {
        return false;  // Null is always falsey.
    }

    public function isAlwaysFalsey(): bool
    {
        return true;  // Null is always falsey.
    }

    public function isAlwaysTruthy(): bool
    {
        return false;  // Null is always falsey.
    }

    public function isPrintableScalar(): bool
    {
        // This would be '', which is probably not intended. allow null in union types for `echo` if there are **other** valid types.
        return Config::get_null_casts_as_any_type();
    }

    public function isValidBitwiseOperand(): bool
    {
        // Allow null in union types for bitwise operations if there are **other** valid types.
        return Config::get_null_casts_as_any_type();
    }

    public function isValidNumericOperand(): bool
    {
        return Config::get_null_casts_as_any_type();
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags): bool
    {
        return self::performComparison(null, $scalar, $flags);
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec(): UnionType
    {
        return IntType::instance(false)->asPHPDocUnionType();
    }

    // TODO: Emit an issue if used for a parameter/property type.
    public function canUseInRealSignature(): bool
    {
        return true;
    }

    public function asScalarType(): ?Type
    {
        return null;
    }

    public function isScalar(): bool
    {
        return false;
    }

    /** @return null */
    public function getValue()
    {
        return null;
    }

    public function asNonLiteralType(): Type
    {
        return $this;
    }

    /** @unused-param $code_base */
    public function weaklyOverlaps(Type $type, CodeBase $code_base): bool
    {
        return $type->isPossiblyFalsey();
    }
}
