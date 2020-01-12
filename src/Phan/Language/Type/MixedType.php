<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

use function class_exists;

/**
 * Represents the PHPDoc `mixed` type, which can cast to/from any type
 *
 * For purposes of analysis, there's usually no difference between mixed and nullable mixed.
 * @phan-pure
 */
class MixedType extends NativeType
{
    /** @phan-override */
    public const NAME = 'mixed';

    // mixed or ?mixed can cast to/from anything.
    // For purposes of analysis, there's usually no difference between mixed and nullable mixed.
    public function canCastToType(Type $unused_type): bool
    {
        return true;
    }

    /**
     * @param Type[] $target_type_set 1 or more types @phan-unused-param
     * @override
     */
    public function canCastToAnyTypeInSet(array $target_type_set): bool
    {
        return true;
    }

    // mixed or ?mixed can cast to/from anything.
    // For purposes of analysis, there's no difference between mixed and nullable mixed.
    protected function canCastToNonNullableType(Type $unused_type): bool
    {
        return true;
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $unused_type): bool
    {
        return true;
    }

    public function isSubtypeOf(Type $type): bool
    {
        return $type instanceof MixedType;
    }

    public function isSubtypeOfNonNullableType(Type $type): bool
    {
        return $type instanceof MixedType;
    }

    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $unused_context,
        CodeBase $unused_code_base
    ): bool {
        // Type casting rules allow mixed to cast to anything.
        // But we don't want `@param mixed $x` to take precedence over `int $x` in the signature.
        return $union_type->hasType($this);
    }

    public function asGenericArrayType(int $key_type): Type
    {
        if ($key_type === GenericArrayType::KEY_INT || $key_type === GenericArrayType::KEY_STRING) {
            return GenericArrayType::fromElementType($this, false, $key_type);
        }
        return ArrayType::instance(false);
    }

    public function isArrayOrArrayAccessSubType(CodeBase $unused_code_base): bool
    {
        return true;
    }

    public function isPrintableScalar(): bool
    {
        return true;  // It's possible.
    }

    public function isValidBitwiseOperand(): bool
    {
        return true;
    }

    public function isValidNumericOperand(): bool
    {
        return true;
    }

    public function isPossiblyObject(): bool
    {
        return true;  // It's possible.
    }

    public function isPossiblyNumeric(): bool
    {
        return true;  // It's possible.
    }

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $unused_context, Type $unused_other): bool
    {
        return true;  // It's possible.
    }

    public function isDefiniteNonObjectType(): bool
    {
        return false;
    }

    public function isDefiniteNonCallableType(): bool
    {
        return false;
    }

    public function canUseInRealSignature(): bool
    {
        return false;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function asObjectType(): ?Type
    {
        return ObjectType::instance(false);
    }

    public function asArrayType(): ?Type
    {
        return ArrayType::instance(false);
    }

    public function asNonFalseyType(): Type
    {
        return NonEmptyMixedType::instance(false);
    }
}
class_exists(NonEmptyMixedType::class);
