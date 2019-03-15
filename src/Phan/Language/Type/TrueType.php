<?php declare(strict_types=1);

namespace Phan\Language\Type;

use AssertionError;
use Phan\Config;
use Phan\Language\Type;

/**
 * Represents the type `true`
 * @see BoolType
 * @see FalseType
 */
final class TrueType extends ScalarType
{
    /** @phan-override */
    const NAME = 'true';

    public function getIsPossiblyTruthy() : bool
    {
        return true;
    }

    public function getIsAlwaysTruthy() : bool
    {
        return true;
    }

    public function getIsPossiblyTrue() : bool
    {
        return true;
    }

    public function getIsAlwaysTrue() : bool
    {
        return !$this->is_nullable;  // If it can be null, it's not **always** identical to true
    }

    public function asNonTrueType() : Type
    {
        if (!$this->is_nullable) {
            throw new AssertionError('should only call asNonTrueType on ?true');
        }
        return NullType::instance(true);
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
        return $this->is_nullable ? (self::_bit_nullable | self::_bit_true) : self::_bit_true;
    }

    public function isPrintableScalar() : bool
    {
        // This would be '1', which is probably not intended
        return Config::getValue('scalar_implicit_cast');
    }

    public function isValidNumericOperand() : bool
    {
        return Config::getValue('scalar_implicit_cast');
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags) : bool
    {
        return self::performComparison(true, $scalar, $flags);
    }

    // public function getTypeAfterIncOrDec() : UnionType - doesn't need to be changed
}
