<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of `int`
 * @see LiteralIntType for Phan's representation of specific integers
 * @phan-pure
 */
class IntType extends ScalarType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'int';

    /** @override */
    public function isPossiblyNumeric(): bool
    {
        return true;
    }

    public function getTypeAfterIncOrDec(): UnionType
    {
        return IntType::instance(false)->asPHPDocUnionType();
    }

    /**
     * Returns the known bounds of this int type as [min, max, is_non_zero].
     * A null bound means that the bound is unknown. Nullability is ignored.
     *
     * @return array{0:?int,1:?int,2:bool}
     */
    public function getIntBounds(): array
    {
        return [null, null, false];
    }

    /**
     * Returns the known bounds of $union_type as [min, max, is_non_zero]
     * if every type in it is a non-nullable int type, or null otherwise.
     * A null bound means that the bound is unknown.
     *
     * @return ?array{0:?int,1:?int,2:bool}
     */
    public static function getIntBoundsOfUnionType(UnionType $union_type): ?array
    {
        $min = null;
        $max = null;
        $is_non_zero = true;
        $is_first = true;
        foreach ($union_type->getTypeSet() as $type) {
            if (!$type instanceof IntType || $type->isNullable()) {
                return null;
            }
            [$type_min, $type_max, $type_is_non_zero] = $type->getIntBounds();
            if ($is_first) {
                $min = $type_min;
                $max = $type_max;
                $is_first = false;
            } else {
                $min = ($min === null || $type_min === null) ? null : \min($min, $type_min);
                $max = ($max === null || $type_max === null) ? null : \max($max, $type_max);
            }
            $is_non_zero = $is_non_zero && $type_is_non_zero;
        }
        if ($is_first) {
            return null;
        }
        return [$min, $max, $is_non_zero];
    }

    /**
     * Returns the int type of `$left $operator $right` for operands that are both non-nullable int types:
     * `positive-int`, `negative-int`, or `non-zero-int` when the sign of the result is known, and `int` otherwise.
     *
     * Overflow to float is not accounted for here. Callers that care include float in the real type set.
     *
     * @param '+'|'-'|'*' $operator
     */
    public static function computeArithmeticResultType(string $operator, UnionType $left, UnionType $right): IntType
    {
        $left_bounds = self::getIntBoundsOfUnionType($left);
        $right_bounds = self::getIntBoundsOfUnionType($right);
        if ($left_bounds === null || $right_bounds === null) {
            return IntType::instance(false);
        }
        [$left_min, $left_max, $left_is_non_zero] = $left_bounds;
        [$right_min, $right_max, $right_is_non_zero] = $right_bounds;
        switch ($operator) {
            case '+':
                return self::fromIntBounds(
                    self::addIntBounds($left_min, $right_min),
                    self::addIntBounds($left_max, $right_max),
                    false
                );
            case '-':
                return self::fromIntBounds(
                    self::addIntBounds($left_min, self::negateIntBound($right_max)),
                    self::addIntBounds($left_max, self::negateIntBound($right_min)),
                    false
                );
            case '*':
                $left_positive = $left_min !== null && $left_min > 0;
                $left_negative = $left_max !== null && $left_max < 0;
                $left_non_negative = $left_min !== null && $left_min >= 0;
                $left_non_positive = $left_max !== null && $left_max <= 0;
                $right_positive = $right_min !== null && $right_min > 0;
                $right_negative = $right_max !== null && $right_max < 0;
                $right_non_negative = $right_min !== null && $right_min >= 0;
                $right_non_positive = $right_max !== null && $right_max <= 0;
                if (($left_positive && $right_positive) || ($left_negative && $right_negative)) {
                    $min = 1;
                    $max = null;
                } elseif (($left_positive && $right_negative) || ($left_negative && $right_positive)) {
                    $min = null;
                    $max = -1;
                } elseif (($left_non_negative && $right_non_negative) || ($left_non_positive && $right_non_positive)) {
                    $min = 0;
                    $max = null;
                } elseif (($left_non_negative && $right_non_positive) || ($left_non_positive && $right_non_negative)) {
                    $min = null;
                    $max = 0;
                } else {
                    $min = null;
                    $max = null;
                }
                return self::fromIntBounds($min, $max, $left_is_non_zero && $right_is_non_zero);
            default:
                return IntType::instance(false);
        }
    }

    /**
     * Returns the int type of `-$expr` where $expr has type $type (e.g. `negative-int` for `positive-int`)
     */
    public static function computeNegatedType(IntType $type): IntType
    {
        [$min, $max, $is_non_zero] = $type->getIntBounds();
        return self::fromIntBounds(self::negateIntBound($max), self::negateIntBound($min), $is_non_zero);
    }

    /**
     * Returns the most specific non-literal int type for the given bounds.
     */
    private static function fromIntBounds(?int $min, ?int $max, bool $is_non_zero): IntType
    {
        if ($min !== null && $min > 0) {
            return PositiveIntType::instance(false);
        }
        if ($max !== null && $max < 0) {
            return NegativeIntType::instance(false);
        }
        if ($is_non_zero) {
            return NonZeroIntType::instance(false);
        }
        return IntType::instance(false);
    }

    /**
     * Adds two bounds, returning null if either is unknown or the sum overflows.
     */
    private static function addIntBounds(?int $a, ?int $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }
        $result = $a + $b;
        return \is_int($result) ? $result : null;
    }

    /**
     * Negates a bound, returning null if it is unknown or the negation overflows.
     */
    private static function negateIntBound(?int $bound): ?int
    {
        if ($bound === null || $bound === \PHP_INT_MIN) {
            return null;
        }
        return -$bound;
    }

    /**
     * Check if this type can possibly cast to the declared type, ignoring nullability of this type
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // always allow int -> float or int -> int
        if ($other instanceof IntType || $other instanceof FloatType || $other instanceof MixedType || $other instanceof TemplateType) {
            return true;
        }
        if ($context->isStrictTypes()) {
            return false;
        }
        return parent::canCastToDeclaredType($code_base, $context, $other);
    }

    public function isPossiblyTruthy(): bool
    {
        return true;
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isAlwaysFalsey(): bool
    {
        return false;
    }

    public function asNonTruthyType(): Type
    {
        return LiteralIntType::instanceForValue(0, $this->is_nullable);
    }

    public function asNonFalseyType(): Type
    {
        return NonZeroIntType::instance(false);
    }

    /**
     * @unused-param $code_base
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return \get_class($type) === self::class || $type instanceof ScalarRawType || $type instanceof MixedType;
    }
}
