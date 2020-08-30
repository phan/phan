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
    /** @phan-override */
    public const NAME = 'object';

    protected function canCastToNonNullableType(Type $type): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isNativeType() && !($type instanceof ArrayType)) {
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isNativeType() && !($type instanceof ArrayType)) {
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type);
    }

    protected function isSubtypeOfNonNullableType(Type $type): bool
    {
        return $type instanceof ObjectType || $type instanceof MixedType;
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

    public function asCallableType(): ?Type
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
