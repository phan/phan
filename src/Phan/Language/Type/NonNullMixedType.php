<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Represents the PHPDoc `non-empty-mixed` type, which can cast to/from any non-null type and is non-null
 *
 * For purposes of analysis, there's usually no difference between mixed and nullable mixed.
 * @phan-pure
 */
final class NonNullMixedType extends MixedType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'non-null-mixed';

    /**
     * @return MixedType
     */
    public static function instance(bool $is_nullable)
    {
        if ($is_nullable) {
            return MixedType::instance(true);
        }
        static $instance = null;
        // @phan-suppress-next-line PhanPartialTypeMismatchReturn Type can't cast to NonNullMixedType
        return $instance ?? ($instance = static::make('\\', self::NAME, [], false, Type::FROM_NODE));
    }

    /**
     * @unused-param $code_base
     */
    public function canCastToType(Type $type, CodeBase $code_base): bool
    {
        return !($type instanceof NullType || $type instanceof VoidType);
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
        return \count($target_type_set) === 0;
    }

    /**
     * @override
     * @unused-param $code_base
     */
    public function canCastToTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return !($type instanceof NullType || $type instanceof VoidType);
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
        return $this->canCastToType($other, $code_base);
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
        return NonEmptyMixedType::instance(false);
    }

    /** @override */
    public function isNullable(): bool
    {
        return false;
    }

    /** @override */
    public function __toString(): string
    {
        return 'non-null-mixed';
    }

    /**
     * @unused-param $other
     * @unused-param $code_base
     */
    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        // e.g. false == null, 0 == null for loose equality.
        return true;
    }

    /**
     * @override
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        return $is_nullable ? MixedType::instance(true) : $this;
    }

    /**
     * @unused-param $code_base
     */
    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        return $type instanceof MixedType && !($type instanceof NonEmptyMixedType);
    }

    /**
     * @unused-param $code_base
     */
    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $type instanceof MixedType && !($type instanceof NonEmptyMixedType);
    }
}
