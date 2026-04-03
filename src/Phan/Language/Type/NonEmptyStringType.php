<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of the type for `non-empty-string`.
 * Excludes '' only. For truthy strings (excludes '' and '0'), see NonFalsyStringType.
 * @phan-pure
 */
class NonEmptyStringType extends StringType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'non-empty-string';

    public function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }

    /** @override */
    public function isPossiblyFalsey(): bool
    {
        // non-empty-string includes '0' which is falsey
        return true;
    }

    /** @override */
    public function isAlwaysTruthy(): bool
    {
        // non-empty-string includes '0' which is falsey
        return false;
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
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return $type->getValue() !== '';
                    }
                    return true;
                case 'non-empty-string':
                case 'non-falsy-string':
                    return true;
                case 'false':
                case 'null':
                    return false;
            }
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @unused-param $code_base
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $type): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return $type->getValue() !== '';
                    }
                    return true;
                case 'non-empty-string':
                case 'non-falsy-string':
                    return true;
            }
            return !$context->isStrictTypes();
        }
        return $type instanceof CallableType || $type instanceof MixedType || $type instanceof TemplateType;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly without config overrides
     * @override
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'non-empty-string':
                case 'non-falsy-string':
                    return true;
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return $type->getValue() !== '';
                    }
                    return true;
            }
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @return bool
     * True if this Type is a subtype of the given type.
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            if ($type instanceof StringType) {
                if ($type instanceof LiteralStringType || $type instanceof CallableStringType) {
                    return false;
                }
                if ($type instanceof NonFalsyStringType) {
                    // non-empty-string is NOT a subtype of non-falsy-string (includes '0')
                    return false;
                }
                return true;
            }
            return false;
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    public function asSignatureType(): Type
    {
        return StringType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                $val = $other->getValue();
                if (\is_string($val)) {
                    return $val !== '';
                }
                if ($val === null) {
                    return $this->is_nullable;
                }
                // '0' weakly equals false, 0, and 0.0 in PHP
                if ($val === false || $val === 0 || $val === 0.0) {
                    return true;
                }
                return (bool)$val;
            }
            return true;
        }
        return parent::weaklyOverlaps($other, $code_base);
    }

    public function asNonFalseyType(): Type
    {
        return NonFalsyStringType::instance(false);
    }

    public function asNonTruthyType(): Type
    {
        // The only falsey value in non-empty-string is '0' (and null if nullable)
        return LiteralStringType::instanceForValue('0', $this->is_nullable);
    }
}
