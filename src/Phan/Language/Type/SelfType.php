<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the PHPDoc type `self`.
 * This is converted to a real class when necessary.
 * @see self::withStaticResolvedInContext()
 * @phan-pure
 */
final class SelfType extends StaticOrSelfType
{
    /** Not an override */
    public const NAME = 'self';

    protected function __construct(bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
    }
    /**
     * Returns a nullable/non-nullable instance of this SelfType
     *
     * @param bool $is_nullable
     * An optional parameter, which if true returns a
     * nullable instance of this native type
     *
     * @return static
     */
    public static function instance(bool $is_nullable): Type
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

    public function isNativeType(): bool
    {
        return false;
    }

    public function isStaticType(): bool
    {
        return false;
    }

    public function isSelfType(): bool
    {
        return true;
    }

    public function __toString(): string
    {
        $string = $this->name;

        if ($this->is_nullable) {
            $string = '?' . $string;
        }

        return $string;
    }

    /**
     * @return Type
     * Either this or 'self' resolved in the given context.
     */
    public function withStaticResolvedInContext(
        Context $context
    ): Type {
        // If the context isn't in a class scope, there's nothing
        // we can do
        if (!$context->isInClassScope()) {
            return $this;
        }
        $type = $context->getClassFQSEN()->asType();
        if ($this->is_nullable) {
            return $type->withIsNullable(true);
        }
        return $type;
    }

    /**
     * @return SelfType
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }
        return self::instance($is_nullable);
    }

    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $context,
        CodeBase $code_base
    ): bool {
        $result = $this->withStaticResolvedInContext($context);
        if ($result !== $this) {
            return $result->isExclusivelyNarrowedFormOrEquivalentTo($union_type, $context, $code_base);
        }
        return false;
    }
}
