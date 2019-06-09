<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;

/**
 * Represents the return type `void`
 */
final class VoidType extends NativeType
{
    /** @phan-override */
    const NAME = 'void';

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

    public function asScalarType() : ?Type
    {
        return null;
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
}
