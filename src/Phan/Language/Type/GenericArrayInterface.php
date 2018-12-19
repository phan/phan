<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\UnionType;

/**
 * This is generated from phpdoc such as array<string,mixed>, array{field:int}, etc.
 */
interface GenericArrayInterface
{
    /** Returns the union type of this generic array type's elements. */
    public function genericArrayElementUnionType() : UnionType;
}
