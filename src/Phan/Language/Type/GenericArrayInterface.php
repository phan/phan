<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\UnionType;

/**
 * This is generated from phpdoc such as array<string,mixed>, array{field:int}, etc.
 * @phan-pure
 */
interface GenericArrayInterface
{
    /** Returns the union type of this generic array type's elements. */
    public function genericArrayElementUnionType() : UnionType;

    /**
     * Returns the key type of this generic or shaped array.
     * e.g. for `int[]`, returns self::KEY_MIXED, for `array<string,mixed>`, returns self::KEY_STRING.
     */
    public function getKeyType() : int;

    /**
     * True for classes such as non-empty-array and non-empty-list.
     */
    public function isDefinitelyNonEmptyArray() : bool;

    /**
     * Is this type nullable
     */
    public function isNullable() : bool;
}
