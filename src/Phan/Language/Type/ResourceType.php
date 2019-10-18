<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Represents the type `resource`
 * @phan-pure
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

    public function canUseInRealSignature() : bool
    {
        return false;
    }

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $unused_context, Type $other) : bool
    {
        // Allow casting resources to other resources.
        return $other instanceof ResourceType;
    }
}
