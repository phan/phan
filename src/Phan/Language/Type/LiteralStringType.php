<?php declare(strict_types=1);
namespace Phan\Language\Type;

use InvalidArgumentException;
use Phan\Config;
use Phan\Language\Type;
use RuntimeException;

/**
 * Phan's representation of the type for a specific string, e.g. `'a string'`
 */
final class LiteralStringType extends StringType implements LiteralTypeInterface
{

    const MINIMUM_MAX_STRING_LENGTH = 50;

    /** @var string $value */
    private $value;

    protected function __construct(string $value, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->value = $value;
    }

    /**
     * Only exists to prevent accidentally calling this on the parent class
     * @internal
     * @deprecated
     * @throws RuntimeException to prevent this from being called
     */
    public static function instance(bool $unused_is_nullable)
    {
        throw new RuntimeException('Call ' . self::class . '::instanceForValue() instead');
    }

    /**
     * @return StringType|LiteralStringType a StringType for $value
     * This will construct an StringType instead if the value is longer than the longest supported string type
     *
     * - This avoids making error messages excessively long
     * - This avoids running out of memory tracking string representations when analyzing code that may build up long strings.
     */
    public static function instanceForValue(string $value, bool $is_nullable)
    {
        if (\strlen($value) > self::MINIMUM_MAX_STRING_LENGTH && \strlen($value) > Config::getValue('max_literal_string_type_length')) {
            // The config can only be used to increase this limit, not decrease it.
            return StringType::instance($is_nullable);
        }

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

    /**
     * @internal - For use within LiteralStringType
     */
    const ESCAPE_CHARACTER_LOOKUP = [
        "\n" => '\\n',
        "\r" => '\\r',
        "\t" => '\\t',
        "\\" => '\\\\',
        "'" =>  '\\\'',
    ];

    /**
     * @internal - For use within LiteralStringType
     */
    const UNESCAPE_CHARACTER_LOOKUP = [
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\\\' => "\\",
        '\\\'' => "'",
    ];

    public function __toString() : string
    {
        // TODO: Finalize escaping
        $inner = \preg_replace_callback(
            '/[^- ,.\/?:;"!#$%^&*_+=a-zA-Z0-9_\x80-\xff]/',
            /**
             * @param array{0:string} $match
             * @return string
             */
            function (array $match) {
                $c = $match[0];
                return self::ESCAPE_CHARACTER_LOOKUP[$c] ?? \sprintf('\\x%02x', \ord($c));
            },
            $this->value
        );
        if ($this->is_nullable) {
            return "?'$inner'";
        }
        return "'$inner'";
    }

    /**
     * The opposite of __toString()
     * @return StringType|LiteralStringType
     * @throws InvalidArgumentException
     * if the $escaped_string is not using the proper escaping
     * (should not happen if UnionType's regex is used)
     */
    public static function fromEscapedString(string $escaped_string, bool $is_nullable) : StringType
    {
        if (\strlen($escaped_string) < 2 || $escaped_string[0] !== "'" || \substr($escaped_string, -1) !== "'") {
            throw new InvalidArgumentException("Expected the literal type string to begin and end with \"'\"");
        }
        $escaped_string = \substr($escaped_string, 1, -1);
        $escaped_string = preg_replace_callback(
            '/\\\\(?:[\'\\\\trn]|x[0-9a-fA-F]{2})/',
            /** @param array{0:string} $matches */
            function (array $matches) : string {
                $x = $matches[0];
                if (\strlen($x) === 2) {
                    // Parses one of \t \r \n \\ \'
                    return self::UNESCAPE_CHARACTER_LOOKUP[$x];
                }
                // convert 2 hex bytes to a single character
                return \chr(\hexdec(\substr($x, 2)));
            },
            $escaped_string
        );
        return self::instanceForValue($escaped_string, $is_nullable);
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
        if ($type instanceof StringType) {
            if ($type instanceof LiteralStringType) {
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

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags) : bool
    {
        return self::performComparison($this->value, $scalar, $flags);
    }
}

LiteralStringType::init();
