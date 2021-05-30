<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Represents the PHPDoc `non-empty-mixed` type, which can cast to/from any non-empty type and is truthy.
 *
 * For purposes of analysis, there's usually no difference between mixed and nullable mixed.
 * @phan-pure
 */
final class NonEmptyMixedType extends MixedType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'non-empty-mixed';

    /**
     * @unused-param $code_base
     */
    public function canCastToType(Type $type, CodeBase $code_base): bool
    {
        return $type->isPossiblyTruthy() || ($this->is_nullable && $type->is_nullable);
    }

    /**
     * @override
     */
    public function canCastToTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return $this->canCastToType($type, $code_base);
    }

    /**
     * @param Type[] $target_type_set 1 or more types @phan-unused-param
     * @override
     */
    public function canCastToAnyTypeInSet(array $target_type_set, CodeBase $code_base): bool
    {
        foreach ($target_type_set as $t) {
            if ($this->canCastToType($t, $code_base)) {
                return true;
            }
        }
        return (bool)$target_type_set;
    }

    /**
     * @unused-param $code_base
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $type->isPossiblyTruthy();
    }

    /**
     * @unused-param $code_base
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return $type->isPossiblyTruthy();
    }

    public function asGenericArrayType(int $key_type): Type
    {
        return GenericArrayType::fromElementType($this, false, $key_type);
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return $other->isPossiblyTruthy();
    }

    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable;
    }

    public function isPossiblyFalse(): bool
    {
        return false;
    }

    public function isAlwaysTruthy(): bool
    {
        return !$this->is_nullable;
    }

    public function asObjectType(): ?Type
    {
        return ObjectType::instance(false);
    }

    public function asArrayType(): ?Type
    {
        return NonEmptyGenericArrayType::fromElementType(
            MixedType::instance(false),
            false,
            GenericArrayType::KEY_MIXED
        );
    }

    public function asNonFalseyType(): Type
    {
        return $this->is_nullable ? $this->withIsNullable(false) : $this;
    }

    /** @override */
    public function isNullable(): bool
    {
        return $this->is_nullable;
    }

    /** @override */
    public function __toString(): string
    {
        return $this->is_nullable ? '?non-empty-mixed' : 'non-empty-mixed';
    }

    /** @unused-param $code_base */
    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        return $other->isPossiblyTruthy();
    }

    public function withIsNullable(bool $is_nullable): Type
    {
        return $is_nullable === $this->is_nullable ? $this : self::instance($is_nullable);
    }

    /**
     * @unused-param $code_base
     */
    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        return $type instanceof MixedType;
    }

    /**
     * @unused-param $code_base
     */
    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $type instanceof MixedType;
    }
}
