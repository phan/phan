<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;

use AssertionError;

final class FalseType extends ScalarType
{
    /** @phan-override */
    const NAME = 'false';

    public function getIsPossiblyFalsey() : bool
    {
        return true;  // it's always falsey, whether or not it's nullable.
    }

    public function getIsAlwaysFalsey() : bool
    {
        return true;  // FalseType is always falsey, whether or not it's nullable.
    }

    public function getIsAlwaysFalse() : bool
    {
        return !$this->is_nullable;  // If it can be null, it's not **always** identical to false
    }

    public function getIsPossiblyTruthy() : bool
    {
        return false;
    }

    public function getIsAlwaysTruthy() : bool
    {
        return false;
    }

    public function getIsPossiblyFalse() : bool
    {
        return true;
    }

    public function asNonFalseType() : Type
    {
        if (!($this->is_nullable)) {
            throw new AssertionError('should only call FalseType->asNonFalseType on ?false');
        }
        return NullType::instance(false);
    }

    public function getIsInBoolFamily() : bool
    {
        return true;
    }

    /**
     * Helper function for internal use by UnionType
     */
    public function getNormalizationFlags() : int
    {
        return $this->is_nullable ? (self::_bit_nullable | self::_bit_false) : self::_bit_false;
    }

    public function isPrintableScalar() : bool
    {
        // This would be '', which is probably not intended
        return Config::getValue('scalar_implicit_cast');
    }

    public function isValidNumericOperand() : bool
    {
        return Config::getValue('scalar_implicit_cast');
    }
}
