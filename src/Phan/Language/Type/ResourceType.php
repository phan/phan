<?php declare(strict_types=1);

namespace Phan\Language\Type;

/**
 * Represents the type `resource`
 */
final class ResourceType extends NativeType
{
    /** @phan-override */
    const NAME = 'resource';

    public function isPrintableScalar() : bool
    {
        return false;
    }

    public function isValidBitwiseOperand() : bool
    {
        return false;
    }
}
