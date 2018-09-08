<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Represents the type `object` (an instance of an unspecified class)
 */
final class ObjectType extends NativeType
{
    /** @phan-override */
    const NAME = 'object';

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isNativeType() && !($type instanceof ArrayType)) {
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }

    /**
     * @return bool
     * True if this type is an object (or the phpdoc `object`)
     * @override
     */
    public function isObject() : bool
    {
        return true;  // Overridden in various subclasses
    }

    /**
     * @override
     */
    public function isObjectWithKnownFQSEN() : bool
    {
        return false;  // Overridden in various subclasses
    }

    /**
     * @return bool
     * True if this type is an object (or the phpdoc `object`)
     * @override
     */
    public function isPossiblyObject() : bool
    {
        return true;
    }
}
