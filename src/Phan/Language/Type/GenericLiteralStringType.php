<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Phan's representation of the type for `literal-string` (a literal string).
 *
 * @see LiteralStringType, which is used for literal strings with known values that are short enough to bother tracking. That class was written first.
 */
class GenericLiteralStringType extends StringType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'literal-string';

    public function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return ($type instanceof StringType || parent::canCastToNonNullableType($type, $code_base)) && !($type instanceof NonEmptyStringType);
    }

    /**
     * @unused-param $code_base
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $type): bool
    {
        return ($type instanceof StringType || parent::canCastToDeclaredType($code_base, $context, $type)) && !($type instanceof NonEmptyStringType);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly without config overrides
     * @override
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        // Allow literal-string -> 'a', string, but forbid literal-string->non-empty-string like string->non-empty-string is forbidden
        return ($type instanceof StringType || parent::canCastToNonNullableTypeWithoutConfig($type, $code_base)) && !($type instanceof NonEmptyStringType);
    }

    /**
     * @return bool
     * True if this Type is a subtype of the given type.
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // E.g. GenericNonEmptyLiteralStringType is a subtype of GenericLiteralStringType
        // (if GenericNonEmptyLiteralStringType were to be added)
        // (TODO: If GenericNonEmptyLiteralStringType allow GenericNonEmptyLiteralStringType->NonEmptyStringType here and above)
        return ($this instanceof $type || parent::isSubtypeOfNonNullableType($type, $code_base)) && !$type instanceof NonEmptyStringType;
    }
}
