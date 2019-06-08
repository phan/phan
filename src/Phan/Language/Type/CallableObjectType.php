<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Represents the type `callable-object` (an instance of an unspecified callable class)
 *
 * This includes Closures and classes with __invoke.
 */
final class CallableObjectType extends ObjectType
{
    /** @phan-override */
    const NAME = 'callable-object';

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // Inverse of check in Type->canCastToNullableType
        if ($type instanceof CallableType) {
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }

    /**
     * @return bool
     * True if this type is a callable
     * @override
     */
    public function isCallable() : bool
    {
        return true;  // Overridden in various subclasses
    }

    // Definitely not possible.
    public function canUseInRealSignature() : bool
    {
        return false;
    }
}
