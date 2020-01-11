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
    /** @phan-override */
    public const NAME = 'callable-object';

    protected function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }

    protected function canCastToNonNullableType(Type $type): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if ($type instanceof CallableType) {
            return true;
        }
        return parent::canCastToNonNullableType($type);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        // Inverse of check in Type->canCastToNullableType
        if ($type instanceof CallableType) {
            return true;
        }
        return parent::canCastToNonNullableTypeWithoutConfig($type);
    }

    /**
     * @return bool
     * True if this type is a callable
     * @override
     */
    public function isCallable(): bool
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

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $unused_context, Type $other): bool
    {
        // TODO: Filter out final classes, etc.
        return $other->isPossiblyObject();
    }
}
