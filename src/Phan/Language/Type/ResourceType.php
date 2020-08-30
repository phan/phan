<?php

declare(strict_types=1);

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
    public const NAME = 'resource';

    public function isPrintableScalar(): bool
    {
        return false;
    }

    public function isValidBitwiseOperand(): bool
    {
        return false;
    }

    public function canUseInRealSignature(): bool
    {
        return false;
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     * @override
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // Allow casting resources to other resources.
        return $other instanceof ResourceType || $other instanceof MixedType;
    }
}
