<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Phan's representation for `callable`
 *
 * @see CallableDeclarationType for Phan's representation of `callable(MyClass):MyOtherClass`
 */
final class CallableType extends NativeType
{
    /** @phan-override */
    const NAME = 'callable';

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
}
