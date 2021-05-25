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

    /**
     * Generates self<A> with template parameter type lists, from phpdoc types such as `(at)return self<A>`
     *
     * @param list<UnionType> $template_parameter_type_list
     *
     * TODO: This is only reachable in trait method definitions - make sure that classes will resolve the trait's template parameter types properly when inheriting methods
     * or handling trait methods defined as (at)return T
     */
    public static function instanceWithTemplateTypeList(bool $is_nullable, array $template_parameter_type_list): SelfType
    {
        if (!$template_parameter_type_list) {
            return self::instance($is_nullable);
        }
        static $map = [];
        $key = ($is_nullable ? 'T' : 'F') . \implode(',', \array_map(static function (UnionType $union_type): string {
            return $union_type->__toString();
        }, $template_parameter_type_list));

        if (isset($map[$key])) {
            return $map[$key];
        }
        $result = new SelfType($is_nullable);
        // @phan-suppress-next-line PhanAccessReadOnlyProperty
        $result->template_parameter_type_list = $template_parameter_type_list;
        return $result;
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
    public function withStaticResolvedInContext(Context $context): Type
    {
        // TODO: Special handling of self<static>, if needed?
        return $this->withSelfResolvedInContext($context);
    }

    /**
     * @return Type
     * 'self' resolved in the given context.
     *
     * TODO: Handle `(at)return OtherType<self>`
     */
    public function withSelfResolvedInContext(Context $context): Type
    {
        // If the context isn't in a class scope, there's nothing
        // we can do
        if (!$context->isInClassScope()) {
            return $this;
        }
        $type = $context->getClassFQSEN()->asType();
        if ($this->template_parameter_type_list) {
            return Type::make(
                $type->namespace,
                $type->name,
                $this->template_parameter_type_list,
                $this->is_nullable,
                Type::FROM_TYPE
            );
        }
        return $type->withIsNullable($this->is_nullable);
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
