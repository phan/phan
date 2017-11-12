<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

// Not sure if it made sense to extend BoolType, so not doing that.
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
        assert($this->is_nullable, 'should only call on ?true');
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
}
