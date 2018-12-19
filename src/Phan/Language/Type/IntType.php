<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\UnionType;

/**
 * Phan's representation of `int`
 * @see LiteralIntType for Phan's representation of specific integers
 */
class IntType extends ScalarType
{
    /** @phan-override */
    const NAME = 'int';

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return true;
    }

    public function getTypeAfterIncOrDec() : UnionType
    {
        return IntType::instance(false)->asUnionType();
    }
}
