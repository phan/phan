<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of the type for `non-falsy-string` (a truthy string).
 * Excludes '' and '0'.
 * @phan-pure
 */
class NonFalsyStringType extends NonEmptyStringType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'non-falsy-string';

    public function __construct(bool $is_nullable)
    {
        // Cannot call parent::__construct because it uses self::NAME
        // which would resolve to 'non-empty-string'
        Type::__construct('\\', self::NAME, [], $is_nullable);
    }

    /** @override */
    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable;
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
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ScalarType) {
            switch ($type::NAME) {
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'non-falsy-string':
                case 'non-empty-string':
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
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'non-falsy-string':
                case 'non-empty-string':
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
                case 'non-falsy-string':
                case 'non-empty-string':
                    return true;
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return (bool)$type->getValue();
                    }
                    return true;
            }
        }

        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
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
                // non-falsy-string is a subtype of non-empty-string and string
                return true;
            }
            return false;
        }

        return parent::isSubtypeOfNonNullableType($type, $code_base);
    }

    public function asSignatureType(): Type
    {
        return StringType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other, CodeBase $code_base): bool
    {
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                $value = $other->getValue();
                // non-falsy-string includes truthy strings like '00', '0.0', '0e0'
                // which are loosely equal to int/float 0, so these overlap
                if (\is_int($value) || \is_float($value)) {
                    return true;
                }
                // @phan-suppress-next-line PhanSuspiciousTruthyString this is intentional for literal type overlap checking
                return $value ? true : $this->is_nullable;
            }
            return true;
        }
        return parent::weaklyOverlaps($other, $code_base);
    }

    public function asNonTruthyType(): Type
    {
        // non-falsy-string is always truthy when not nullable;
        // the only falsey possibility is null (when nullable)
        return NullType::instance(false);
    }

    public function asNonFalseyType(): Type
    {
        return $this->withIsNullable(false);
    }
}
