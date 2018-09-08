<?php declare(strict_types=1);
namespace Phan\Language\Type;

/**
 * Phan's representation of the type for `float`
 */
final class FloatType extends ScalarType
{
    /** @phan-override */
    const NAME = 'float';

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return true;
    }
}
