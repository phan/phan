<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of the type for `float`
 * @phan-pure
 */
class FloatType extends ScalarType
{
    /** @phan-override */
    public const NAME = 'float';

    /** @override */
    public function isPossiblyNumeric(): bool
    {
        return true;
    }

    public function isValidBitwiseOperand(): bool
    {
        return Config::getValue('scalar_implicit_cast');
    }

    public function isPossiblyTruthy(): bool
    {
        return true;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isAlwaysFalsey(): bool
    {
        return false;
    }

    public function getTypeAfterIncOrDec(): UnionType
    {
        return FloatType::instance(false)->asPHPDocUnionType();
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // Allow casting scalars to other scalars, but not to null.
        if ($other instanceof ScalarType) {
            return $other instanceof FloatType || (!$context->isStrictTypes() && parent::canCastToDeclaredType($code_base, $context, $other));
        }
        return $other instanceof CallableType ||
            $other instanceof TemplateType ||
            $other instanceof MixedType;
    }
}
