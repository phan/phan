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
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'mixed';

    /**
     * mixed or ?mixed can cast to/from anything.
     * For purposes of analysis, there's usually no difference between mixed and nullable mixed.
     *
     * @unused-param $type
     * @unused-param $code_base
     * @override
     */
    public function canCastToType(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * @param Type[] $target_type_set 1 or more types @phan-unused-param
     * @unused-param $code_base
     * @override
     */
    public function canCastToAnyTypeInSet(array $target_type_set, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * Overridden in NonNullMixedType and NonEmptyMixedType
     * @unused-param $type
     * @unused-param $code_base
     * @override
     */
    public function canCastToTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * mixed or ?mixed can cast to/from anything even if nullable.
     * For purposes of analysis, there's usually no difference between mixed and nullable mixed.
     *
     * @unused-param $type
     * @unused-param $code_base
     * @override
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * mixed or ?mixed can cast to/from anything even if nullable.
     * For purposes of analysis, there's usually no difference between mixed and nullable mixed.
     *
     * @unused-param $type
     * @unused-param $code_base
     * @override
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    // FIXME: non-empty-mixed/non-null-mixed is a subtype of mixed, but not vice versa?
    /**
     * @unused-param $code_base
     */
    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        return \get_class($type) === MixedType::class;
    }

    /**
     * @unused-param $code_base
     */
    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return \get_class($type) === MixedType::class;
    }

    /**
     * @unused-param $context
     * @unused-param $code_base
     * @override
     */
    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $context,
        CodeBase $code_base
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

    /**
     * @unused-param $code_base
     * @override
     */
    public function isArrayOrArrayAccessSubType(CodeBase $code_base): bool
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

    /**
     * @suppress PhanUnusedPublicMethodParameter
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return true;  // It's possible.
    }

    public function isDefiniteNonObjectType(): bool
    {
        return false;
    }

    /**
     * @unused-param $code_base
     */
    public function isDefiniteNonCallableType(CodeBase $code_base): bool
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

    public function isPossiblyFalse(): bool
    {
        return true;
    }

    public function isPossiblyTrue(): bool
    {
        return true;
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

    /** Overridden by NonEmptyMixedType */
    public function isNullable(): bool
    {
        return true;
    }

    public function isNullableLabeled(): bool
    {
        return $this->is_nullable;
    }

    /** Overridden by NonEmptyMixedType */
    public function __toString(): string
    {
        return $this->is_nullable ? '?mixed' : 'mixed';
    }

    /**
     * @unused-param $other
     * @unused-param $code_base
     */
    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        return true;
    }

    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable) {
            if ($this->is_nullable) {
                return $this;
            }
            return static::instance(false);
        }
        return NonNullMixedType::instance(false);
    }

    public function asScalarType(): ?Type
    {
        return ScalarRawType::instance(false);
    }
}
class_exists(NonEmptyMixedType::class);
class_exists(NonNullMixedType::class);
