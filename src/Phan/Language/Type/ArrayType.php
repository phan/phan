<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class ArrayType extends IterableType
{
    /** @phan-override */
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
// Trigger the autoloader for GenericArrayType so that it won't be called
// before ArrayType.
// This won't pass if GenericArrayType is in the process of being instantiated.
\class_exists(GenericArrayType::class);
