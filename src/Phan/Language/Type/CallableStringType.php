<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation for `callable-string`
 *
 * @see CallableDeclarationType for Phan's representation of `callable(MyClass):MyOtherClass`
 * @phan-pure
 */
final class CallableStringType extends StringType implements CallableInterface
{
    /** @phan-override */
    public const NAME = 'callable-string';

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     */
    public function isCallable(): bool
    {
        return true;
    }

    protected function canCastToNonNullableType(Type $type): bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof CallableDeclarationType;
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableTypeWithoutConfig($type) || $type instanceof CallableDeclarationType;
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, string, array, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType(): bool
    {
        return false;
    }

    /** @override */
    public function isPossiblyNumeric(): bool
    {
        return false;
    }

    /**
     * Returns the type after an expression such as `++$x`
     */
    public function getTypeAfterIncOrDec(): UnionType
    {
        return UnionType::fromFullyQualifiedPHPDocString('string');
    }

    public function canUseInRealSignature(): bool
    {
        return false;
    }

    public function isAlwaysTruthy(): bool
    {
        return !$this->is_nullable;
    }

    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable;
    }

    public function isPossiblyObject(): bool
    {
        return false;
    }

    protected function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }

    /**
     * Returns a nullable/non-nullable instance of this CallableStringType
     *
     * @param bool $is_nullable
     * An optional parameter, which if true returns a
     * nullable instance of this native type
     *
     * @return static
     */
    public static function instance(bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if ($nullable_instance === null) {
                $nullable_instance = new self(true);
            }

            return $nullable_instance;
        }

        static $instance;

        if (!$instance) {
            $instance = new self(false);
        }
        return $instance;
    }

    /**
     * @return CallableStringType
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }
        return self::instance($is_nullable);
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return $other instanceof StringType ||
            $other instanceof MixedType ||
            $other instanceof TemplateType ||
            $other instanceof CallableType;
    }
}
