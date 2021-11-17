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
        return $type instanceof self || parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @unused-param $code_base
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $type): bool
    {
        return $type instanceof self || parent::canCastToDeclaredType($code_base, $context, $type);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly without config overrides
     * @override
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return $type instanceof self || parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
    }

    /**
     * @return bool
     * True if this Type is a subtype of the given type.
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // E.g. GenericNonEmptyLiteralStringType is a subtype of GenericLiteralStringType
        return $this instanceof $type || parent::isSubtypeOfNonNullableType($type, $code_base);
    }
}
