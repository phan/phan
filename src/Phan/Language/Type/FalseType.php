<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use AssertionError;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of PHPDoc `false`
 * @see TrueType
 * @see BoolType
 * @phan-pure
 */
final class FalseType extends ScalarType
{
    /** @phan-override */
    public const NAME = 'false';

    public function isPossiblyFalsey(): bool
    {
        return true;  // it's always falsey, whether or not it's nullable.
    }

    public function isAlwaysFalsey(): bool
    {
        return true;  // FalseType is always falsey, whether or not it's nullable.
    }

    public function isAlwaysFalse(): bool
    {
        return !$this->is_nullable;  // If it can be null, it's not **always** identical to false
    }

    public function isPossiblyTruthy(): bool
    {
        return false;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isPossiblyFalse(): bool
    {
        return true;
    }

    public function asNonFalseType(): Type
    {
        if (!($this->is_nullable)) {
            throw new AssertionError('should only call FalseType->asNonFalseType on ?false');
        }
        return NullType::instance(false);
    }

    public function isInBoolFamily(): bool
    {
        return true;
    }

    /**
     * Helper function for internal use by UnionType
     */
    public function getNormalizationFlags(): int
    {
        return $this->is_nullable ? (self::_bit_nullable | self::_bit_false) : self::_bit_false;
    }

    public function isPrintableScalar(): bool
    {
        // This would be '', which is probably not intended
        return Config::getValue('scalar_implicit_cast');
    }

    public function isValidNumericOperand(): bool
    {
        return Config::getValue('scalar_implicit_cast');
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags): bool
    {
        return self::performComparison(false, $scalar, $flags);
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return $other->isInBoolFamily() ||
            \get_class($other) === MixedType::class ||
            $other instanceof TemplateType ||
            (!$context->isStrictTypes() && parent::canCastToDeclaredType($code_base, $context, $other));
    }


    // public function getTypeAfterIncOrDec() : UnionType - doesn't need to be changed
    /**
     * Returns the corresponding type that would be used in a signature
     * @override
     */
    public function asSignatureType(): Type
    {
        return BoolType::instance($this->is_nullable);
    }
}
