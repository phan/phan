<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Represents the type `object` (an instance of an unspecified class)
 * @phan-pure
 */
class ObjectType extends NativeType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'object';

    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isNativeType() && !($type instanceof ArrayType)) {
            return true;
        }
        return parent::canCastToNonNullableType($type, $code_base);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isNativeType() && !($type instanceof ArrayType)) {
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
    }

    /** @unused-param $code_base */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return \get_class($type) === ObjectType::class || $type instanceof MixedType;
    }

    /**
     * @return bool
     * True if this type is an object (or the phpdoc `object`)
     * @override
     */
    public function isObject(): bool
    {
        return true;  // Overridden in various subclasses
    }

    /**
     * @override
     */
    public function isObjectWithKnownFQSEN(): bool
    {
        return false;  // Overridden in various subclasses
    }

    /**
     * @return bool
     * True if this type is an object (or the phpdoc `object`)
     * @override
     */
    public function isPossiblyObject(): bool
    {
        return true;
    }

    /**
     * Check if this type can possibly cast to the declared type, ignoring nullability of this type
     * @unused-param $code_base
     * @unused-param $context
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        return $other->isPossiblyObject();
    }

    public function canUseInRealSignature(): bool
    {
        // Callers should check this separately if they want to support php 7.2
        return false;
    }

    /** For ObjectType/CallableObjectType  */
    public function asObjectType(): ?Type
    {
        return $this->withIsNullable(false);
    }

    /**
     * @unused-param $code_base
     */
    public function asCallableType(CodeBase $code_base): ?Type
    {
        return CallableObjectType::instance(false);
    }

    /**
     * @unused-param $code_base
     */
    public function asIterable(CodeBase $code_base): ?Type
    {
        return Type::traversableInstance();
    }
}
\class_exists(CallableObjectType::class);
