<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the type `string`.
 * @see LiteralStringType for the representation of types for specific string literals
 * @phan-pure
 */
class StringType extends ScalarType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'string';

    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type, $code_base) || $type instanceof CallableDeclarationType;
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base) || $type instanceof CallableDeclarationType;
    }

    /**
     * @unused-param $code_base
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return \get_class($type) === self::class || $type instanceof ScalarRawType || $type instanceof MixedType;
    }

    /** @override */
    public function isPossiblyNumeric(): bool
    {
        return true;
    }

    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // Allow casting scalars to other scalars, but not to null.
        if ($other instanceof ScalarType) {
            return $other instanceof StringType || (!$context->isStrictTypes() && parent::canCastToDeclaredType($code_base, $context, $other));
        }
        return $other instanceof CallableType ||
            $other instanceof TemplateType ||
            $other instanceof MixedType;
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, string, array, object, iterable, T, etc.
     * @unused-param $code_base
     */
    public function isDefiniteNonCallableType(CodeBase $code_base): bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec(): UnionType
    {
        return UnionType::fromFullyQualifiedPHPDocString('int|string|float');
    }

    public function isValidNumericOperand(): bool
    {
        if (Config::getValue('scalar_implicit_cast')) {
            return true;
        }
        $string_casts = Config::getValue('scalar_implicit_partial')['string'] ?? null;
        if (!\is_array($string_casts)) {
            return false;
        }
        return \in_array('int', $string_casts, true) || \in_array('float', $string_casts, true);
    }

    public function isPossiblyFalsey(): bool
    {
        return true;
    }

    public function isPossiblyTruthy(): bool
    {
        return true;
    }

    public function isAlwaysFalsey(): bool
    {
        return false;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    /**
     * @unused-param $code_base
     */
    public function asCallableType(CodeBase $code_base): ?Type
    {
        return CallableStringType::instance(false);
    }

    public function asNonFalseyType(): Type
    {
        return NonEmptyStringType::instance(false);
    }
}
\class_exists(ClassStringType::class);
\class_exists(NonEmptyStringType::class);
\class_exists(CallableStringType::class);
