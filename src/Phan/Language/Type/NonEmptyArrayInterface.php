<?php declare(strict_types=1);

namespace Phan\Language\Type;

/**
 * Common functionality for types such as `non-empty-list` and `non-empty-array`
 * @phan-pure
 */
interface NonEmptyArrayInterface
{
    /**
     * Convert this to the related type that's allowed to be empty
     */
    public function asPossiblyEmptyArrayType() : ArrayType;
}
