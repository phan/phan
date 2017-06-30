<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class ArrayType extends IterableType
{
    const NAME = 'array';
    public function getIsAlwaysTruthy() : bool
    {
        return false;
    }

    public function asNonTruthyType() : Type
    {
        // There's no EmptyArrayType, so return $this
        return $this;
    }
}
