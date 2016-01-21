<?php declare(strict_types=1);
namespace Phan\Language\Type;

abstract class ScalarType extends NativeType
{
    public function isScalar() : bool
    {
        return true;
    }
}
