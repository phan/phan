<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the return type `void`
 */
final class VoidType extends NativeType
{
    /** @phan-override */
    const NAME = 'void';

    /**
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param array<int,UnionType> $template_parameter_type_list @phan-unused-param
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
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     */
    public function isDefiniteNonObjectType() : bool
    {
        return true;
    }

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType() : bool
    {
        return true;
    }

    public function canCastToNonNullableType(Type $_) : bool
    {
        // null_casts_as_any_type means that null or nullable can cast to any type?
        // But don't allow it for void?
        return false;
    }

    public function canUseInRealSignature() : bool
    {
        return true;
    }

    public function isSubtypeOf(Type $type) : bool
    {
        return $type->isNullable();
    }

    public function isSubtypeOfNonNullableType(Type $unused_type) : bool
    {
        return false;
    }


    public function asScalarType() : ?Type
    {
        return null;
    }

    public function withIsNullable(bool $unused_is_nullable) : Type
    {
        return $this;
    }

    public function __toString() : string
    {
        return self::NAME;
    }

    public function isNullable() : bool
    {
        return true;
    }

    public function isScalar() : bool
    {
        return false;
    }

    public function isPossiblyFalsey() : bool
    {
        return true;  // Null is always falsey.
    }

    public function isPossiblyTruthy() : bool
    {
        return false;  // Null is always falsey.
    }

    public function isAlwaysFalsey() : bool
    {
        return true;  // Null is always falsey.
    }

    public function isAlwaysTruthy() : bool
    {
        return false;  // Null is always falsey.
    }


    public function isPrintableScalar() : bool
    {
        // This would be '', which is probably not intended. allow null in union types for `echo` if there are **other** valid types.
        return Config::get_null_casts_as_any_type();
    }

    public function isValidBitwiseOperand() : bool
    {
        // Allow null in union types for bitwise operations if there are **other** valid types.
        return Config::get_null_casts_as_any_type();
    }

    public function isValidNumericOperand() : bool
    {
        return Config::get_null_casts_as_any_type();
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags) : bool
    {
        return self::performComparison(null, $scalar, $flags);
    }
}
