<?php declare(strict_types=1);
namespace Phan\Language\Type;

class IntType extends ScalarType
{
    /** @phan-override */
    const NAME = 'int';

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return true;
    }
}
