<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class StringType extends ScalarType
{
    /** @phan-override */
    const NAME = 'string';

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof CallableDeclarationType;
    }

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return true;
    }
}
