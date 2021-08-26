<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;
use RuntimeException;

/**
 * Phan's representation of the type for a specific integer, e.g. `-1`
 * @phan-pure
 */
final class LiteralIntType extends IntType implements LiteralTypeInterface
{
    use NativeTypeTrait;

    /** @var int $value */
    private $value;

    protected function __construct(int $value, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->value = $value;
    }

    /**
     * Only exists to prevent accidentally calling this
     * @unused-param $is_nullable
     * @internal - do not call
     * @deprecated
     * @return never
     */
    public static function instance(bool $is_nullable)
    {
        throw new RuntimeException('Call ' . self::class . '::instanceForValue() instead');
    }

    /**
     * @return LiteralIntType a unique LiteralIntType for $value (and the nullability)
     */
    public static function instanceForValue(int $value, bool $is_nullable): LiteralIntType
    {
        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$value] ?? ($nullable_cache[$value] = new self($value, true));
        }
        static $cache = [];
        return $cache[$value] ?? ($cache[$value] = new self($value, false));
    }

    /**
     * Returns the literal int that this type represents
     * (whether or not this type is nullable)
     */
    public function getValue(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if ($this->is_nullable) {
            return '?' . $this->value;
        }
        return (string)$this->value;
    }

    /** @var IntType the non-nullable int type instance. */
    private static $non_nullable_int_type;
    /** @var IntType the nullable int type instance. */
    private static $nullable_int_type;

    /**
     * Called at the bottom of the file to ensure static properties are set for quick access.
     */
    public static function init(): void
    {
        self::$non_nullable_int_type = IntType::instance(false);
        self::$nullable_int_type = IntType::instance(true);
    }

    public function asNonLiteralType(): Type
    {
        return $this->is_nullable ? self::$nullable_int_type : self::$non_nullable_int_type;
    }

    /**
     * @return Type[]
     * @override
     */
    public function withFlattenedArrayShapeOrLiteralTypeInstances(): array
    {
        return [$this->is_nullable ? self::$nullable_int_type : self::$non_nullable_int_type];
    }

    public function hasArrayShapeOrLiteralTypeInstances(): bool
    {
        return true;
    }

    /** @override */
    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable || !$this->value;
    }

    /** @override */
    public function isAlwaysFalsey(): bool
    {
        return !$this->value;
    }

    /** @override */
    public function isPossiblyTruthy(): bool
    {
        return (bool)$this->value;
    }

    /** @override */
    public function isAlwaysTruthy(): bool
    {
        return (bool)$this->value && !$this->is_nullable;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'int':
                    if ($type instanceof LiteralIntType) {
                        return $type->value === $this->value;
                    }
                    return true;
                case 'non-zero-int':
                    return (bool)$this->value;
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        if ($type->getValue() != $this->value) {
                            // Do a loose equality comparison and check if that permits that
                            // E.g. can't cast 5 to 'foo', but can cast 5 to '5' or '5foo' depending on the other rules
                            return false;
                        }
                    }
                    break;
                case 'float':
                    if ($type instanceof LiteralFloatType) {
                        return $type->getValue() == $this->value;
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

        return parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'int':
                    if ($type instanceof LiteralIntType) {
                        return $type->value === $this->value;
                    }
                    return true;
                case 'float':
                    if ($type instanceof LiteralFloatType) {
                        return $type->getValue() == $this->value;
                    }
                    return true;
                case 'non-zero-int':
                    return (bool)$this->value;
                default:
                    return false;
            }
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @return bool
     * True if this Type is a subtype of the given type
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            if ($type instanceof IntType) {
                if ($type instanceof LiteralIntType) {
                    return $type->value === $this->value;
                }
                if ($type instanceof NonZeroIntType) {
                    return (bool)$this->value;
                }
                return true;
            }
            return false;
        }

        return parent::isSubtypeOfNonNullableType($type, $code_base);
    }

    /**
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return self::instanceForValue(
            $this->value,
            $is_nullable
        );
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags): bool
    {
        return self::performComparison($this->value, $scalar, $flags);
    }

    public function asSignatureType(): Type
    {
        return IntType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                return $other->getValue() == $this->value;
            }
            return $this->value ? ($this->is_nullable || $other->isPossiblyTruthy()) : $other->isPossiblyFalsey();
        }
        return parent::weaklyOverlaps($other, $code_base);
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        if ($other instanceof LiteralIntType) {
            return $other->value === $this->value;
        } elseif ($other instanceof NonZeroIntType) {
            return (bool)$this->value;
        }
        return parent::canCastToDeclaredType($code_base, $context, $other);
    }

    public function asNonFalseyType(): Type
    {
        return $this->value ? $this->withIsNullable(false) : NonZeroIntType::instance(false);
    }

    public function asNonTruthyType(): Type
    {
        return $this->value ? NullType::instance(false) : $this;
    }
}

LiteralIntType::init();
