<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Represents the phpdoc utility type `positive-int`.
 */
final class PositiveIntType extends IntType
{
    use NativeTypeTrait;

    public const NAME = 'positive-int';

    protected function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }

    /** @override */
    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable;
    }

    /** @override */
    public function isAlwaysTruthy(): bool
    {
        return !$this->is_nullable;
    }

    /** @override */
    protected function canCastToNonNullableType(Type $type, \Phan\CodeBase $code_base): bool
    {
        if ($type instanceof LiteralIntType) {
            return $type->getValue() > 0;
        }
        if ($type instanceof IntRangeType) {
            $lower = $type->getLowerBound();
            return $lower === null || $lower > 0;
        }
        if ($type instanceof NonZeroIntType || $type instanceof IntType) {
            return true;
        }
        return parent::canCastToNonNullableType($type, $code_base);
    }

    /** @override */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, \Phan\CodeBase $code_base): bool
    {
        if ($type instanceof LiteralIntType) {
            return $type->getValue() > 0;
        }
        if ($type instanceof IntRangeType) {
            $lower = $type->getLowerBound();
            return $lower === null || $lower > 0;
        }
        if ($type instanceof NonZeroIntType || $type instanceof IntType) {
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
    }

    /** @override */
    public function asSignatureType(): Type
    {
        return IntType::instance($this->is_nullable);
    }
}
