<?php declare(strict_types=1);

namespace Phan\Language\Type;

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
}
