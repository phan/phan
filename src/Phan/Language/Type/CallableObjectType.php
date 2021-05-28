<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;

/**
 * Represents the type `callable-object` (an instance of an unspecified callable class)
 *
 * This includes Closures and classes with __invoke.
 * @phan-pure
 */
final class CallableObjectType extends ObjectType
{
    use NativeTypeTrait;

    /** @phan-override */
    public const NAME = 'callable-object';

    protected function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }

    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isPossiblyObject()) {
            return false;
        }
        if ($type instanceof CallableInterface) {
            return true;
        }
        return parent::canCastToNonNullableType($type, $code_base);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if (!$type->isPossiblyObject()) {
            return false;
        }
        if ($type instanceof CallableInterface) {
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
    }

    /**
     * @return bool
     * True if this type is a callable
     * @override
     * @unused-param $code_base
     */
    public function isCallable(CodeBase $code_base): bool
    {
        return true;  // Overridden in various subclasses
    }

    // Definitely not possible.
    public function canUseInRealSignature(): bool
    {
        return false;
    }

    /**
     * Returns a nullable/non-nullable instance of this CallableObjectType
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
     * @return CallableObjectType
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
        // TODO: Filter out final classes, etc.
        return $other->isPossiblyObject() && !$other->isDefiniteNonCallableType($code_base);
    }

    /**
     * @unused-param $code_base
     */
    protected function isSubtypeOfNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $type instanceof CallableType || $type instanceof ObjectType || $type instanceof MixedType;
    }
}
