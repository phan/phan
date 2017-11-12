<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\UnionType;
use Phan\Language\Type;

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
}

// Temporary hack to load FalseType and TrueType before BoolType::instance() is called
// (Due to bugs in php static variables)
\class_exists(FalseType::class);
\class_exists(TrueType::class);
