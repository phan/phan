<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the type `string`.
 * @see LiteralStringType for the representation of types for specific string literals
 */
class StringType extends ScalarType
{
    /** @phan-override */
    const NAME = 'string';

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof CallableDeclarationType;
    }

    /** @override */
    public function isPossiblyNumeric() : bool
    {
        return true;
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, string, array, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType() : bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec() : UnionType
    {
        return UnionType::fromFullyQualifiedPHPDocString('int|string|float');
    }

    public function isValidNumericOperand() : bool
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

    public function isPossiblyFalsey() : bool
    {
        return true;
    }

    public function isPossiblyTruthy() : bool
    {
        return true;
    }

    public function isAlwaysFalsey() : bool
    {
        return false;
    }

    public function isAlwaysTruthy() : bool
    {
        return false;
    }

    public function asCallableType() : ?Type
    {
        return CallableStringType::instance(false);
    }
}
\class_exists(ClassStringType::class);
\class_exists(CallableStringType::class);
