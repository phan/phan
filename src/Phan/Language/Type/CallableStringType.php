<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation for `callable-string`
 *
 * @see CallableDeclarationType for Phan's representation of `callable(MyClass):MyOtherClass`
 */
final class CallableStringType extends StringType implements CallableInterface
{
    /** @phan-override */
    const NAME = 'callable-string';

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     */
    public function isCallable() : bool
    {
        return true;
    }

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof CallableDeclarationType;
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, string, array, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType() : bool
    {
        return false;
    }

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec() : UnionType
    {
        return UnionType::fromFullyQualifiedString('string');
    }
}
