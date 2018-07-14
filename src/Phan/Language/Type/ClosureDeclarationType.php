<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

final class ClosureDeclarationType extends FunctionLikeDeclarationType
{
    /** @override */
    const NAME = 'Closure';

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type) : bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                return $this->canCastToNonNullableFunctionLikeDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }
}
