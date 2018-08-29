<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

use RuntimeException;

final class LiteralIntType extends IntType implements LiteralTypeInterface
{
    /** @var int $value */
    private $value;

    protected function __construct(int $value, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->value = $value;
    }

    /**
     * @internal - Only exists to prevent accidentally calling this
     * @deprecated
     */
    public static function instance(bool $unused_is_nullable)
    {
        throw new RuntimeException('Call ' . __CLASS__ . '::instanceForValue() instead');
    }

    /**
     * @return LiteralIntType
     */
    public static function instanceForValue(int $value, bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$value] ?? ($nullable_cache[$value] = new self($value, true));
        }
        static $cache = [];
        return $cache[$value] ?? ($cache[$value] = new self($value, false));
    }

    public function getValue() : int
    {
        return $this->value;
    }

    public function __toString() : string
    {
        if ($this->is_nullable) {
            return '?' . $this->value;
        }
        return (string)$this->value;
    }

    /** @var IntType */
    private static $non_nullable_int_type;
    /** @var IntType */
    private static $nullable_int_type;

    public static function init()
    {
        self::$non_nullable_int_type = IntType::instance(false);
        self::$nullable_int_type = IntType::instance(true);
    }

    public function asNonLiteralType() : Type
    {
        return $this->is_nullable ? self::$nullable_int_type : self::$non_nullable_int_type;
    }

    /**
     * @return Type[]
     * @override
     */
    public function withFlattenedArrayShapeOrLiteralTypeInstances() : array
    {
        return [$this->is_nullable ? self::$nullable_int_type : self::$non_nullable_int_type];
    }

    public function hasArrayShapeOrLiteralTypeInstances() : bool
    {
        return true;
    }

    /** @override */
    public function getIsPossiblyFalsey() : bool
    {
        return !$this->value;
    }

    /** @override */
    public function getIsAlwaysFalsey() : bool
    {
        return !$this->value;
    }

    /** @override */
    public function getIsPossiblyTruthy() : bool
    {
        return (bool)$this->value;
    }

    /** @override */
    public function getIsAlwaysTruthy() : bool
    {
        return (bool)$this->value;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof IntType) {
            if ($type instanceof LiteralIntType) {
                return $type->getValue() === $this->getValue();
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
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
    public function withIsNullable(bool $is_nullable) : Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return self::instanceForValue(
            $this->value,
            $is_nullable
        );
    }
}

LiteralIntType::init();
