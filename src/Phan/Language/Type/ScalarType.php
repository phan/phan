<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * The base class for various scalar types (BoolType, StringType, ScalarRawType,
 * NullType (null is technically not a scalar, but included), etc.
 * @phan-pure
 */
abstract class ScalarType extends NativeType
{
    public function isScalar(): bool
    {
        return true;
    }

    public function isPrintableScalar(): bool
    {
        return true;  // Overridden in subclass BoolType
    }

    public function isValidBitwiseOperand(): bool
    {
        return true;
    }

    public function isSelfType(): bool
    {
        return false;
    }

    public function isStaticType(): bool
    {
        return false;
    }

    /**
     * @unused-param $code_base
     */
    public function isArrayLike(CodeBase $code_base): bool
    {
        return false;
    }

    public function isGenericArray(): bool
    {
        return false;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // Scalars may be configured to always cast to each other.
        // NOTE: This deliberately includes NullType, which doesn't satisfy `is_scalar()`
        if ($type instanceof ScalarType) {
            if (Config::getValue('scalar_implicit_cast')) {
                return true;
            }
            $scalar_implicit_partial = Config::getValue('scalar_implicit_partial');
            if (\count($scalar_implicit_partial) > 0) {
                // check if $type->getName() is in the list of permitted types $this->getName() can cast to.
                // Both this and $type are NativeType and getName() isn't needed
                if (\in_array($type->name, $scalar_implicit_partial[$this->name] ?? [], true)) {
                    return true;
                }
            }
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    // inherit canCastToNonNullableTypeWithoutConfig

    /**
     * @override
     */
    public function asFQSENString(): string
    {
        return $this->name;
    }

    public function isAlwaysTruthy(): bool
    {
        // Most scalars (Except ResourceType) have a false value, e.g. 0/""/"0"/0.0/false.
        // (But ResourceType isn't a subclass of ScalarType in Phan's implementation)
        return false;
    }

    public function asNonTruthyType(): Type
    {
        // Subclasses of ScalarType all have false values within their types.
        return $this;
    }

    /**
     * @override
     */
    public function shouldBeReplacedBySpecificTypes(): bool
    {
        return false;
    }

    public function isValidNumericOperand(): bool
    {
        return true;
    }

    /**
     * @unused-param $code_base
     * @unused-param $context
     * @override
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        // Allow casting scalars to other scalars, but not to null.
        if ($other instanceof ScalarType) {
            return !($other instanceof NullType);
        }
        return $other instanceof MixedType || $other instanceof TemplateType;
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

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     * @unused-param $code_base
     */
    public function isDefiniteNonCallableType(CodeBase $code_base): bool
    {
        return true;
    }

    public function asScalarType(): ?Type
    {
        return $this->withIsNullable(false);
    }

    /**
     * @unused-param $code_base
     * @unused-param $class_type
     * @override
     */
    public function canPossiblyCastToClass(CodeBase $code_base, Type $class_type): bool
    {
        return false;
    }

    /** @override */
    public function eraseTemplatesRecursive(): Type
    {
        return $this;
    }
}
\class_exists(IntType::class);
\class_exists(StringType::class);
