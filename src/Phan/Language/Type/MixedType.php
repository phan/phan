<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

final class MixedType extends NativeType
{
    /** @phan-override */
    const NAME = 'mixed';

    // mixed or ?mixed can cast to/from anything.
    // For purposes of analysis, there's no difference between mixed and nullable mixed.
    public function canCastToType(Type $unused_type) : bool {
        return true;
    }

    // mixed or ?mixed can cast to/from anything.
    // For purposes of analysis, there's no difference between mixed and nullable mixed.
    protected function canCastToNonNullableType(Type $unused_type) : bool {
        return true;
    }

    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $unused_context,
        CodeBase $unused_code_base
    ) : bool {
        // Type casting rules allow mixed to cast to anything.
        // But we don't want `@param mixed $x` to take precedence over `int $x` in the signature.
        return $union_type->hasType($this);
    }
}
