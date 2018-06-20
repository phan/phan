<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * This is generated from phpdoc such as array{field:int}
 */
interface GenericArrayInterface
{
    public function genericArrayElementUnionType() : UnionType;

    public function genericArrayElementType() : Type;
}
