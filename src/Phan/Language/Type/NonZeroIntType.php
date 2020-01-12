<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of a non-zero-int.
 * @phan-pure
 */
final class NonZeroIntType extends IntType
{
    public const NAME = 'non-zero-int';

    /** @var int $value */
    private $value;

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
    public function isAlwaysFalsey(): bool
    {
        return false;
    }

    /** @override */
    public function isPossiblyTruthy(): bool
    {
        return true;
    }

    /** @override */
    public function isAlwaysTruthy(): bool
    {
        return !$this->is_nullable;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'int':
                    if ($type instanceof LiteralIntType) {
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'non-zero-int':
                    return true;
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return (bool)$type->getValue();
                    }
                    break;
                case 'float':
                    if ($type instanceof LiteralFloatType) {
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'true':
                    if (!$this->value) {
                        return false;
                    }
                    break;
                case 'false':
                    if ($this->value) {
                        return false;
                    }
                    break;
                case 'null':
                    // null is also a scalar.
                    if ($this->value && !Config::get_null_casts_as_any_type()) {
                        return false;
                    }
                    break;
            }
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'int':
                    if ($type instanceof LiteralIntType) {
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'float':
                    if ($type instanceof LiteralFloatType) {
                        return (bool)$type->getValue();
                    }
                    return true;
                default:
                    return false;
            }
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @return bool
     * True if this Type is a subtype of the given type
     */
    protected function isSubtypeOfNonNullableType(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            if ($type instanceof IntType) {
                if ($type instanceof LiteralIntType) {
                    return (bool)$type->getValue();
                }
                return true;
            }
            return false;
        }

        return parent::canCastToNonNullableType($type);
    }

    public function asSignatureType(): Type
    {
        return IntType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                return (bool)$other->getValue();
            }
            if (!$other->isPossiblyTruthy()) {
                return $this->is_nullable;
            }
            return true;
        }
        return parent::weaklyOverlaps($other);
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        if ($other instanceof LiteralIntType) {
            return (bool)$other->getValue();
        } elseif ($other instanceof NonZeroIntType) {
            return true;
        }
        return parent::canCastToDeclaredType($code_base, $context, $other);
    }

    public function asNonTruthyType(): Type
    {
        return $this->is_nullable ? NullType::instance(false) : LiteralIntType::instanceForValue(0, true);
    }
}
