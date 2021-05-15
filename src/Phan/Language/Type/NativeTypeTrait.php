<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's base class for native types such as IntType, ObjectType, etc.
 *
 * (i.e. not class instances, Closures, etc)
 * @phan-pure
 */
trait NativeTypeTrait
{
    /**
     * @param bool $is_nullable
     * If true, returns a nullable instance of this native type
     *
     * @return static
     * Returns a nullable/non-nullable instance of this native type (possibly unchanged)
     *
     * @suppress PhanAbstractStaticMethodCallInTrait this is deliberate - this will only be called on classes using the trait
     * @suppress PhanUndeclaredConstantOfClass also deliberate
     */
    public static function instance(bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if ($nullable_instance === null) {
                $nullable_instance = static::make('\\', static::NAME, [], true, Type::FROM_NODE);
            }

            return $nullable_instance;
        }

        static $instance = null;

        if ($instance === null) {
            $instance = static::make('\\', static::NAME, [], false, Type::FROM_NODE);
        }

        return $instance;
    }

    /**
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param string $type_name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param list<UnionType> $template_parameter_type_list
     * A (possibly empty) list of template parameter types
     *
     * @param bool $is_nullable
     * True if this type can be null, false if it cannot
     * be null.
     *
     * @param int $source
     * Type::FROM_NODE, Type::FROM_TYPE, or Type::FROM_PHPDOC
     * (E.g. outside of phpdoc, "integer" would be a class name)
     *
     * @return Type
     * A single canonical instance of the given type.
     *
     * Overridden in some subclasses but not others.
     */
    abstract protected static function make(
        string $namespace,
        string $type_name,
        array $template_parameter_type_list,
        bool $is_nullable,
        int $source
    ): Type;
}
