<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of `int`
 * @see LiteralIntType for Phan's representation of specific integers
 * @phan-pure
 */
class IntType extends ScalarType
{
    /** @phan-override */
    const NAME = 'int';

    /** @override */
    public function isPossiblyNumeric() : bool
    {
        return true;
    }

    public function getTypeAfterIncOrDec() : UnionType
    {
        return IntType::instance(false)->asPHPDocUnionType();
    }

    /**
     * Check if this type can possibly cast to the declared type, ignoring nullability of this type
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other) : bool
    {
        // always allow int -> float or int -> int
        if ($other instanceof IntType || $other instanceof FloatType) {
            return true;
        }
        if ($context->isStrictTypes()) {
            return false;
        }
        return parent::canCastToDeclaredType($code_base, $context, $other);
    }

    public function isPossiblyTruthy() : bool
    {
        return true;
    }

    public function isPossiblyFalsey() : bool
    {
        return true;
    }

    public function isAlwaysTruthy() : bool
    {
        return false;
    }

    public function isAlwaysFalsey() : bool
    {
        return false;
    }
}
