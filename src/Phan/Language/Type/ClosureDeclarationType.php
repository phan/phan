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
    use NativeTypeTrait;

    /** @override */
    public const NAME = 'Closure';

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if (!$type->isPossiblyObject() || $type->isDefiniteNonCallableType($code_base)) {
            return false;
        }
        if ($type->isCallable($code_base)) {
            if ($type instanceof FunctionLikeDeclarationType) {
                return $this->canCastToNonNullableFunctionLikeDeclarationType($type, $code_base);
            }
            return true;
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    public function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        if (!$type->isPossiblyObject()) {
            return false;
        }
        if ($type->isCallable($code_base)) {
            if ($type instanceof FunctionLikeDeclarationType) {
                return $this->canCastToNonNullableFunctionLikeDeclarationType($type, $code_base);
            }
            return true;
        }

        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
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
        if ($other->isDefiniteNonCallableType($code_base)) {
            return false;
        }
        if ($other instanceof IterableType) {
            return false;
        }
        if ($other->hasObjectWithKnownFQSEN()) {
            // Probably overkill to check for intersection types for closure
            return $other->anyTypePartsMatchCallback(static function (Type $part): bool {
                return $part instanceof FunctionLikeDeclarationType || $part instanceof ClosureType || $part->asFQSEN()->__toString() === '\Closure';
            });
        }
        return true;
    }

    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if (!$type->isPossiblyObject()) {
            return false;
        }
        return parent::isSubtypeOfNonNullableType($type, $code_base);
    }
}
