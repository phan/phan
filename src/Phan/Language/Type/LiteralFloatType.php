<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;
use RuntimeException;

/**
 * Phan's representation of the type for a specific float, e.g. `-1.2`
 * @phan-pure
 */
final class LiteralFloatType extends FloatType implements LiteralTypeInterface
{
    use NativeTypeTrait;

    /** @var float $value */
    private $value;

    protected function __construct(float $value, bool $is_nullable)
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
     * @return FloatType a unique LiteralFloatType for $value if $value is finite (and sets nullability)
     */
    public static function instanceForValue(float $value, bool $is_nullable): FloatType
    {
        if (!\is_finite($value)) {
            // Don't want to represent INF, -INF, NaN
            return FloatType::instance($is_nullable);
        }
        $key = \var_export($value, true);
        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$key] ?? ($nullable_cache[$key] = new self($value, true));
        }
        static $cache = [];
        return $cache[$key] ?? ($cache[$key] = new self($value, false));
    }

    /**
     * Returns the literal float that this type represents
     * (whether or not this type is nullable)
     */
    public function getValue(): float
    {
        return $this->value;
    }

    public function __toString(): string
    {
        $str = \var_export($this->value, true);
        if ($this->is_nullable) {
            return '?' . $str;
        }
        return $str;
    }

    /** @var FloatType the non-nullable float type instance. */
    private static $non_nullable_float_type;
    /** @var FloatType the nullable float type instance. */
    private static $nullable_float_type;

    /**
     * Called at the bottom of the file to ensure static properties are set for quick access.
     */
    public static function init(): void
    {
        self::$non_nullable_float_type = FloatType::instance(false);
        self::$nullable_float_type = FloatType::instance(true);
    }

    public function asNonLiteralType(): Type
    {
        return $this->is_nullable ? self::$nullable_float_type : self::$non_nullable_float_type;
    }

    /**
     * @return Type[]
     * @override
     */
    public function withFlattenedArrayShapeOrLiteralTypeInstances(): array
    {
        return [$this->is_nullable ? self::$nullable_float_type : self::$non_nullable_float_type];
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
                case 'float':
                    if ($type instanceof LiteralFloatType) {
                        return $type->value === $this->value;
                    }
                    return true;
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        if ($type->getValue() != $this->value) {
                            // Do a loose equality comparison and check if that permits that
                            // E.g. can't cast 5 to 'foo', but can cast 5 to '5' or '5foo' depending on the other rules
                            return false;
                        }
                    }
                    break;
                case 'int':
                    return false;
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
     * cleanly, ignoring permissive config casting rules
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'float':
                    if ($type instanceof LiteralFloatType) {
                        return $type->value === $this->value;
                    }
                    return true;
                default:
                    return false;
            }
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            if ($type::NAME === 'float') {
                if ($type instanceof LiteralFloatType) {
                    return $type->value === $this->value;
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
     * @param float|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags): bool
    {
        return self::performComparison($this->value, $scalar, $flags);
    }

    public function asSignatureType(): Type
    {
        return FloatType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                return $other->getValue() == $this->value;
            }
            if ($other instanceof NullType || $other instanceof FalseType) {
                // Allow 0 == null but not 1 == null
                if (!$this->isPossiblyFalsey()) {
                    return false;
                }
            }
            return true;
        }
        return parent::weaklyOverlaps($other, $code_base);
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        if ($other instanceof LiteralFloatType) {
            return $other->value === $this->value;
        }
        return parent::canCastToDeclaredType($code_base, $context, $other);
    }

    public function asNonTruthyType(): Type
    {
        return $this->value ? NullType::instance(false) : $this;
    }

    /**
     * Returns true if the value can be used in bitwise operands and cast to integers without precision loss.
     *
     * @override
     */
    public function isValidBitwiseOperand(): bool
    {
        return \fmod($this->value, 1.0) === 0.0 && $this->value >= -0xffffffffffffffff && $this->value <= 0xffffffffffffffff;
    }
}

LiteralFloatType::init();
