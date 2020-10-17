<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use AssertionError;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the PHPDoc type `static`.
 * This is converted to a real class when necessary.
 * @see self::withStaticResolvedInContext()
 * @phan-pure
 */
final class StaticType extends StaticOrSelfType
{
    /** Not an override */
    public const NAME = 'static';

    /**
     * Returns a nullable/non-nullable instance of this StaticType
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
                $nullable_instance = static::make('\\', static::NAME, [], true, Type::FROM_TYPE);
            }

            if (!($nullable_instance instanceof static)) {
                throw new AssertionError('Expected StaticType::make to return StaticType');
            }
            return $nullable_instance;
        }

        static $instance;

        if (!$instance) {
            $instance = static::make('\\', static::NAME, [], false, Type::FROM_TYPE);
            if (!($instance instanceof static)) {
                throw new AssertionError('Expected StaticType::make to return StaticType');
            }
        }
        return $instance;
    }

    /**
     * Generates static<A> with template parameter type lists, from phpdoc types such as `(at)return static<A>`
     *
     * @param list<UnionType> $template_parameter_type_list
     */
    public static function instanceWithTemplateTypeList(bool $is_nullable, array $template_parameter_type_list): StaticType
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

        $instance = static::make('\\', static::NAME, $template_parameter_type_list, false, Type::FROM_TYPE);
        if (!($instance instanceof static)) {
            throw new AssertionError('Expected StaticType::make to return StaticType');
        }
        return $instance;
    }


    public function isNativeType(): bool
    {
        return false;
    }

    public function isSelfType(): bool
    {
        return false;
    }

    public function isStaticType(): bool
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
     * Either this or 'static' resolved in the given context.
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
        if ($this->template_parameter_type_list) {
            return $type->make(
                $type->namespace,
                $type->name,
                $this->template_parameter_type_list,
                $type->is_nullable,
                Type::FROM_TYPE
            );
        }
        return $type->withIsNullable($this->is_nullable);
    }

    /**
     * @return StaticType
     */
    public function withIsNullable(bool $is_nullable): Type
    {
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
