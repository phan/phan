<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation for annotations such as `Closure(MyClass):MyOtherClass`
 * @see ClosureType for the representation of `Closure` (and closures for function-like FQSENs)
 * @phan-pure
 */
final class ClosureDeclarationType extends FunctionLikeDeclarationType
{
    /** @override */
    public const NAME = 'Closure';

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type): bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                return $this->canCastToNonNullableFunctionLikeDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    public function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                return $this->canCastToNonNullableFunctionLikeDeclarationType($type);
            }
            return true;
        }

        return parent::canCastToNonNullableTypeWithoutConfig($type);
    }

    /**
     * Returns the corresponding type that would be used in a signature
     * @override
     */
    public function asSignatureType(): Type
    {
        return ClosureType::instance($this->is_nullable);
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // TODO: Apply the inverse to objects with known fqsens - stdClass is not a closure
        if (!$other->isPossiblyObject()) {
            return false;
        }
        if ($other->isObjectWithKnownFQSEN()) {
            return $other instanceof FunctionLikeDeclarationType || $other instanceof ClosureType || $other->asFQSEN()->__toString() === '\Closure';
        }
        return true;
    }
}
