<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

final class StaticType extends Type
{
    /** Not an override */
    const NAME = 'static';

    /**
     * @param bool $is_nullable
     * An optional parameter, which if true returns a
     * nullable instance of this native type
     *
     * @return static
     */
    public static function instance(bool $is_nullable) : Type
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if (empty($nullable_instance)) {
                $nullable_instance = static::make('\\', static::NAME, [], true, Type::FROM_TYPE);
            }

            \assert($nullable_instance instanceof static);
            return $nullable_instance;
        }

        static $instance;

        if (empty($instance)) {
            $instance = static::make('\\', static::NAME, [], false, Type::FROM_TYPE);
            \assert($instance instanceof static);
        } else {
            \assert($instance instanceof static);
        }
        return $instance;
    }

    public function isNativeType() : bool
    {
        return false;
    }

    public function isSelfType() : bool
    {
        return false;
    }

    public function isStaticType() : bool
    {
        return true;
    }

    public function isObject() : bool
    {
        return true;
    }

    public function __toString() : string
    {
        $string = $this->name;

        if ($this->getIsNullable()) {
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
    ) : Type {
        // If the context isn't in a class scope, there's nothing
        // we can do
        if (!$context->isInClassScope()) {
            return $this;
        }
        $type = $context->getClassFQSEN()->asType();
        if ($this->getIsNullable()) {
            return $type->withIsNullable(true);
        }
        return $type;
    }

    public function isExclusivelyNarrowedFormOrEquivalentTo(
        UnionType $union_type,
        Context $context,
        CodeBase $code_base
    ) : bool {
        $result = $this->withStaticResolvedInContext($context);
        if ($result !== $this) {
            return $result->isExclusivelyNarrowedFormOrEquivalentTo($union_type, $context, $code_base);
        }
        return false;
    }
}
