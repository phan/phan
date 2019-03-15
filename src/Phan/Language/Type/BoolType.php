<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;

/**
 * Phan's representation of the type for `bool`.
 *
 * @see TrueType
 * @see FalseType
 */
final class BoolType extends ScalarType
{
    /** @phan-override */
    const NAME = 'bool';
    public function getIsPossiblyFalsey() : bool
    {
        return true;  // it's always falsey, since this is conceptually a collection of FalseType and TrueType
    }

    public function asNonFalseyType() : Type
    {
        return TrueType::instance(false);
    }

    public function asNonTruthyType() : Type
    {
        return FalseType::instance($this->is_nullable);
    }

    public function getIsPossiblyFalse() : bool
    {
        return true;  // it's possibly false, since this is conceptually a collection of FalseType and TrueType
    }

    public function asNonFalseType() : Type
    {
        return TrueType::instance($this->is_nullable);
    }

    public function getIsPossiblyTrue() : bool
    {
        return true;  // it's possibly true, since this is conceptually a collection of FalseType and TrueType
    }

    public function asNonTrueType() : Type
    {
        return FalseType::instance($this->is_nullable);
    }

    public function getIsInBoolFamily() : bool
    {
        return true;
    }

    public function getIsAlwaysTruthy() : bool
    {
        return false;  // overridden in various types. This base class (Type) is implicitly the type of an object, which is always truthy.
    }

    /**
     * Helper function for internal use by UnionType
     */
    public function getNormalizationFlags() : int
    {
        return $this->is_nullable ? (self::_bit_nullable | self::_bit_true | self::_bit_false) : (self::_bit_true | self::_bit_false);
    }

    public function isPrintableScalar() : bool
    {
        // This would be '' or '1', which is probably not intended
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
        return self::performComparison(false, $scalar, $flags) ||
            self::performComparison(true, $scalar, $flags);
    }
}

// Temporary hack to load FalseType and TrueType before BoolType::instance() is called
// (Due to bugs in php static variables)
\class_exists(FalseType::class);
\class_exists(TrueType::class);
