<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Phan's representation for types such as `callable(MyClass):MyOtherClass`
 * @phan-pure
 */
final class CallableDeclarationType extends FunctionLikeDeclarationType implements CallableInterface
{
    /** @override */
    public const NAME = 'callable';

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type): bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                // TODO: Weaker mode to allow callable to cast to Closure
                return $type instanceof CallableDeclarationType && $this->canCastToNonNullableFunctionLikeDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    public function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                // TODO: Weaker mode to allow callable to cast to Closure
                return $type instanceof CallableDeclarationType && $this->canCastToNonNullableFunctionLikeDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableTypeWithoutConfig($type);
    }

    /**
     * @override to prevent Phan from emitting PhanUndeclaredTypeParameter when using this in phpdoc
     */
    public function isNativeType(): bool
    {
        return true;
    }

    /**
     * Returns the corresponding type that would be used in a signature
     * @override
     */
    public function asSignatureType(): Type
    {
        return CallableType::instance($this->is_nullable);
    }
}
