<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

final class ObjectType extends NativeType
{
    const NAME = 'object';

    protected function canCastToNonNullableType(Type $type) : bool {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isNativeType() && !($type instanceof ArrayType)) {
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }
}
