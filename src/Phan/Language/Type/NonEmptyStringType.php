<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of the type for `non-empty-string` (a truthy string)
 * @phan-pure
 */
final class NonEmptyStringType extends StringType
{
    /** @phan-override */
    public const NAME = 'non-empty-string';

    public function __construct(bool $is_nullable)
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
                case 'string':
                    if ($type instanceof LiteralStringType) {
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'non-empty-string':
                    return true;
                case 'false':
                case 'null':
                    return false;
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
                        return (bool)$type->getValue();
                    }
                    return true;
                case 'non-empty-string':
                    return true;
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
                case 'non-empty-string':
                    return true;
                case 'string':
                    if ($type instanceof LiteralStringType) {
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
     * True if this Type is a subtype of the given type.
     */
    protected function isSubtypeOfNonNullableType(Type $type): bool
    {
        if ($type instanceof ScalarType) {
            if ($type instanceof StringType) {
                if ($type instanceof LiteralStringType) {
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
        return StringType::instance($this->is_nullable);
    }

    public function weaklyOverlaps(Type $other): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if ($other instanceof LiteralTypeInterface) {
                return (bool)$other->getValue();
            }
            if ($other instanceof NullType || $other instanceof FalseType) {
                // Allow 0 == null but not 1 == null
                if (!$this->isPossiblyFalsey()) {
                    return false;
                }
            }
            return true;
        }
        return parent::weaklyOverlaps($other);
    }
}
