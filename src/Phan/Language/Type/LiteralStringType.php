<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use InvalidArgumentException;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Type;
use Phan\Language\UnionType;
use RuntimeException;

use function filter_var;

use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

/**
 * Phan's representation of the type for a specific string, e.g. `'a string'`
 * @phan-pure
 * @phan-file-suppress PhanSuspiciousTruthyString helpers to check truthiness
 */
final class LiteralStringType extends StringType implements LiteralTypeInterface
{

    public const MINIMUM_MAX_STRING_LENGTH = 50;

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
     * Check if Phan will represent strings of a given length in its type system.
     * @param int|float $length
     */
    public static function canRepresentStringOfLength($length): bool
    {
        // The config can only be used to increase this limit, not decrease it.
        return $length <= self::MINIMUM_MAX_STRING_LENGTH || $length <= Config::getValue('max_literal_string_type_length');
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
        if (!self::canRepresentStringOfLength(\strlen($value))) {
            return StringType::instance($is_nullable);
        }

        if ($is_nullable) {
            static $nullable_cache = [];
            return $nullable_cache[$value] ?? ($nullable_cache[$value] = new self($value, true));
        }
        static $cache = [];
        return $cache[$value] ?? ($cache[$value] = new self($value, false));
    }

    /**
     * Returns the literal string this type represents (whether or not this is the nullable type)
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @internal - For use within LiteralStringType
     */
    private const ESCAPE_CHARACTER_LOOKUP = [
        "\n" => '\\n',
        "\r" => '\\r',
        "\t" => '\\t',
        "\\" => '\\\\',
        "'" =>  '\\\'',
    ];

    /**
     * @internal - For use within LiteralStringType
     */
    private const UNESCAPE_CHARACTER_LOOKUP = [
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\\\' => "\\",
        '\\\'' => "'",
    ];

    public function __toString(): string
    {
        // TODO: Finalize escaping
        // NOTE: Phan has issues with parsing commas in phpdoc, so don't suggest them.
        // Support for commas should be restored when https://github.com/phan/phan/issues/2597 is fixed
        $inner = \preg_replace_callback(
            '/[^- .\/?:;"!#$%^&*_+=a-zA-Z0-9_\x80-\xff]/',
            /**
             * @param array{0:string} $match
             */
            static function (array $match): string {
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
    public static function fromEscapedString(string $escaped_string, bool $is_nullable): StringType
    {
        if (\strlen($escaped_string) < 2 || $escaped_string[0] !== "'" || \substr($escaped_string, -1) !== "'") {
            throw new InvalidArgumentException("Expected the literal type string to begin and end with \"'\"");
        }
        $escaped_string = \substr($escaped_string, 1, -1);
        $escaped_string = \preg_replace_callback(
            '/\\\\(?:[\'\\\\trn]|x[0-9a-fA-F]{2})/',
            /** @param array{0:string} $matches */
            static function (array $matches): string {
                $x = $matches[0];
                if (\strlen($x) === 2) {
                    // Parses one of \t \r \n \\ \'
                    return self::UNESCAPE_CHARACTER_LOOKUP[$x];
                }
                // convert 2 hex bytes to a single character
                // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal, PhanPartialTypeMismatchArgumentInternal
                return \chr(\hexdec(\substr($x, 2)));
            },
            // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal
            $escaped_string
        );
        return self::instanceForValue($escaped_string, $is_nullable);
    }

    /** @var StringType the non-nullable string type instance. */
    private static $non_nullable_string_type;
    /** @var StringType the nullable string type instance. */
    private static $nullable_string_type;

    /**
     * Called at the bottom of the file to ensure static properties are set for quick access.
     */
    public static function init(): void
    {
        self::$non_nullable_string_type = StringType::instance(false);
        self::$nullable_string_type = StringType::instance(true);
    }

    public function asNonLiteralType(): Type
    {
        return $this->is_nullable ? self::$nullable_string_type : self::$non_nullable_string_type;
    }

    /**
     * @return Type[]
     * @override
     */
    public function withFlattenedArrayShapeOrLiteralTypeInstances(): array
    {
        return [$this->is_nullable ? self::$nullable_string_type : self::$non_nullable_string_type];
    }

    public function hasArrayShapeOrLiteralTypeInstances(): bool
    {
        return true;
    }

    /** @override */
    public function isPossiblyFalsey(): bool
    {
        return !$this->value || $this->is_nullable;
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
    protected function canCastToNonNullableType(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return $type->value === $this->value;
                    }
                    return true;
                case 'int':
                    // Allow int or float strings to cast to int or floats
                    if (filter_var($this->value, FILTER_VALIDATE_INT) === false) {
                        return false;
                    }
                    break;
                case 'float':
                    if (filter_var($this->value, FILTER_VALIDATE_FLOAT) === false) {
                        return false;
                    }
                    break;
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
                case 'non-empty-string':
                    return (bool)$this->value;
            }
        }

        return parent::canCastToNonNullableType($type);
    }

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $context, Type $type): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        if ($this->value != $type->value) {
                            return false;
                        }
                    }
                    return true;
                case 'non-empty-string':
                    return (bool)$this->value;
                case 'int':
                    // Allow int or float strings to cast to int or floats
                    if (filter_var($this->value, FILTER_VALIDATE_INT) === false) {
                        return false;
                    }
                    break;
                case 'float':
                    // Allow int or float strings to cast to int or floats
                    if (filter_var($this->value, FILTER_VALIDATE_FLOAT) === false) {
                        return false;
                    }
                    break;
            }
            return !$context->isStrictTypes();
        }
        return $type instanceof CallableType;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly without config overrides
     * @override
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return $type->value === $this->value;
                    }
                    return true;
                case 'non-empty-string':
                    return (bool)$this->value;
                default:
                    return false;
            }
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @return bool
     * True if this Type is a subtype of the given type.
     */
    protected function isSubtypeOfNonNullableType(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            if ($type instanceof StringType) {
                if ($type instanceof LiteralStringType) {
                    return $type->value === $this->value;
                } elseif ($type instanceof NonEmptyStringType) {
                    return (bool)$this->value;
                }
                return true;
            }
            return false;
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

    /**
     * If a literal string is numeric, then it will have a numeric type (int/float) after being incremented/decremented
     */
    public function getTypeAfterIncOrDec(): UnionType
    {
        $v = $this->value;
        ++$v;
        return Type::nonLiteralFromObject($v)->asPHPDocUnionType();
    }

    /**
     * Returns the function interface that would be used if this type's string were a callable, or null.
     * @param CodeBase $code_base the code base in which the function interface is found
     * @param Context $context the context where the function interface is referenced (for emitting issues)
     */
    public function asFunctionInterfaceOrNull(CodeBase $code_base, Context $context): ?FunctionInterface
    {
        // parse 'function_name' or 'class_name::method_name'
        // NOTE: In other subclasses of Type, calling this might recurse.
        $function_like_fqsens = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $this->value, true);
        return $function_like_fqsens[0] ?? null;
    }

    public function isValidNumericOperand(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_FLOAT) !== false;
    }

    public function asSignatureType(): Type
    {
        return StringType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                return $other->getValue() == $this->value;
            }
            // Allow 0 == null but not 1 == null
            return $this->value ? ($this->is_nullable || $other->isPossiblyTruthy()) : $other->isPossiblyFalsey();
        }
        return parent::weaklyOverlaps($other);
    }

    public function asNonFalseyType(): Type
    {
        return $this->value ? $this->withIsNullable(false) : NonEmptyStringType::instance(false);
    }
}

LiteralStringType::init();
