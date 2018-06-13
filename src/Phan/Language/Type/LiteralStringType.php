<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

use RuntimeException;

final class LiteralStringType extends StringType implements LiteralTypeInterface
{
    /** @var string $value */
    private $value;

    protected function __construct(string $value, bool $is_nullable)
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
        throw new RuntimeException('Call ' . __CLASS__ . '::instance_for_value() instead');
    }

    public static function instance_for_value(string $value, bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$value] ?? ($nullable_cache[$value] = new self($value, true));
        }
        static $cache = [];
        return $cache[$value] ?? ($cache[$value] = new self($value, false));
    }

    public function getValue() : string
    {
        return $this->value;
    }

    public function __toString() : string
    {
        // TODO: Finalize escaping
        $as_string = "'" . \addcslashes($this->value, "'\\") . "'";
        if ($this->is_nullable) {
            return '?' . $as_string;
        }
        return $as_string;
    }

    /** @var StringType */
    private static $non_nullable_int_type;
    /** @var StringType */
    private static $nullable_int_type;

    public static function init()
    {
        self::$non_nullable_int_type = StringType::instance(false);
        self::$nullable_int_type = StringType::instance(true);
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
        if ($type instanceof LiteralStringType) {
            return $type->getValue() === $this->getValue();
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

        return self::instance_for_value(
            $this->value,
            $is_nullable
        );
    }
}

LiteralStringType::init();
