<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Type;

/**
 * Phan's representation for types such as `callable(MyClass):MyOtherClass`
 * @phan-pure
 */
final class CallableDeclarationType extends FunctionLikeDeclarationType implements CallableInterface
{
    use NativeTypeTrait;

    /** @override */
    public const NAME = 'callable';

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $this->isCompatibleCallable($type, $code_base) ?? parent::canCastToNonNullableType($type, $code_base);
    }

    public function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return $this->isCompatibleCallable($type, $code_base) ?? parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
    }

    private function isCompatibleCallable(Type $type, CodeBase $code_base): ?bool
    {
        if ($type->isCallable($code_base)) {
            // TODO: More precise intersection type support
            if ($type instanceof FunctionLikeDeclarationType) {
                // TODO: Weaker mode to allow callable to cast to Closure
                return $type instanceof CallableDeclarationType && $this->canCastToNonNullableFunctionLikeDeclarationType($type, $code_base);
            }
            return true;
        }
        return null;
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
