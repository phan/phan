<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\UnionType;

/**
 * Phan's representation for `class-string`
 */
final class ClassStringType extends StringType
{
    /** @phan-override */
    const NAME = 'class-string';

    /** @override */
    public function getIsPossiblyNumeric() : bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec() : UnionType
    {
        return UnionType::fromFullyQualifiedString('string');
    }
}
