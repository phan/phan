<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class MixedType extends NativeType
{
    const NAME = 'mixed';

    // mixed or ?mixed can cast to/from anything.
    // For purposes of analysis, there's no difference between mixed and nullable mixed.
    public function canCastToType(Type $type) : bool {
        return true;
    }

    // mixed or ?mixed can cast to/from anything.
    // For purposes of analysis, there's no difference between mixed and nullable mixed.
    protected function canCastToNonNullableType(Type $type) : bool {
        return true;
    }
}
