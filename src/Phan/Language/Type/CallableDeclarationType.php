<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

final class CallableDeclarationType extends FunctionLikeDeclarationType
{
    /** @override */
    const NAME = 'callable';

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type) : bool
    {
        if ($type->isCallable()) {
            if ($this->getIsNullable()) {
                return false;
            }
            if ($type instanceof FunctionLikeDeclarationType) {
                // TODO: Weaker mode to allow callable to cast to Closure
                return $type instanceof CallableDeclarationType && $this->canCastToNonNullableFunctionLikeDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @override to stop PhanUndeclaredTypeParameter
     */
    public function isNativeType() : bool
    {
        return true;
    }
}
