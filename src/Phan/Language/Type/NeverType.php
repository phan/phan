<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the return type `never` in phpdoc signatures (and in php 8.1 in https://wiki.php.net/rfc/never_type)
 *
 * > In type theory never would be called a "bottom" type.
 * > That means it's effectively a subtype of every other type in PHP’s type system, including void.
 *
 * @phan-pure
 *
 * @phan-file-suppress PhanUnusedPublicFinalMethodParameter
 */
final class NeverType extends NativeType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'never';

    /**
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param list<UnionType> $template_parameter_type_list @phan-unused-param
     * A (possibly empty) list of template parameter types
     *
     * @param bool $is_nullable (@phan-unused-param)
     */
    protected function __construct(
        string $namespace,
        string $name,
        $template_parameter_type_list,
        bool $is_nullable
    ) {
        parent::__construct(
            $namespace,
            $name,
            [],
            false
        );
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     * @unused-param $other
     * @override
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return true;
    }

    /**
     * `never` is a subtype of every type
     * @unused-param $type
     */
    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * `never` is a subtype of every type
     *
     * @unused-param $type
     */
    public function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     */
    public function isDefiniteNonObjectType(): bool
    {
        return true;
    }

    public function canCastToTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType(CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly.
     */
    public function canCastToType(Type $type, CodeBase $code_base): bool
    {
        // never can cast to any type.
        // Plugins should be used to warn about using the result of an expression returning never
        return true;
    }

    /**
     * @unused-param $type
     * @override
     */
    public function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * @unused-param $type
     * @override
     */
    public function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return true;
    }

    /**
     * @unused-param $is_nullable
     * @override
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        return $is_nullable ? NullType::instance(false) : $this;
    }

    public function __toString(): string
    {
        return self::NAME;
    }

    public function isNullable(): bool
    {
        return false;
    }

    public function isNullableLabeled(): bool
    {
        return false;
    }

    public function isPossiblyFalsey(): bool
    {
        return false;
    }

    public function isPossiblyTruthy(): bool
    {
        return false;
    }

    public function isAlwaysFalsey(): bool
    {
        return false;
    }

    public function isAlwaysTruthy(): bool
    {
        return false;
    }

    public function isPrintableScalar(): bool
    {
        return false;
    }

    public function isValidBitwiseOperand(): bool
    {
        return false;
    }

    public function isValidNumericOperand(): bool
    {
        return false;
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar @unused-param
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER) @unused-param
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags): bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec(): UnionType
    {
        return UnionType::empty();
    }

    // TODO: Emit an issue if used for a parameter/property type.
    public function canUseInRealSignature(): bool
    {
        return true;
    }

    public function asScalarType(): ?Type
    {
        return null;
    }

    public function isScalar(): bool
    {
        return false;
    }
}
