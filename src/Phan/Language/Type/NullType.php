<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Config;
use Phan\Language\Type;
use Phan\Language\UnionType;

final class NullType extends ScalarType
{
    /** @phan-override */
    const NAME = 'null';

    /**
     * @param string $name
     * The name of the type such as 'int' or 'MyClass'
     *
     * @param string $namespace
     * The (optional) namespace of the type such as '\'
     * or '\Phan\Language'.
     *
     * @param UnionType[] $template_parameter_type_list
     * A (possibly empty) list of template parameter types
     *
     * @param bool $is_nullable (@phan-unused-param)
     * True if this type can be null, false if it cannot
     * be null. (NullType can always be null)
     */
    protected function __construct(
        string $namespace,
        string $name,
        $template_parameter_type_list,
        bool $is_nullable
    ) {
        parent::__construct(
            $namespace,
            $name,
            $template_parameter_type_list,
            true
        );
    }

    public function canCastToNonNullableType(Type $type) : bool
    {
        // null_casts_as_any_type means that null or nullable can cast to any type?
        return Config::get_null_casts_as_any_type()
            || (Config::get_null_casts_as_array() && $type->isArrayLike())
            || parent::canCastToNonNullableType($type);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    public function canCastToType(Type $type) : bool
    {
        // Check to see if we have an exact object match
        if ($this === $type) {
            return true;
        }

        // Null can cast to a nullable type.
        if ($type->getIsNullable()) {
            return true;
        }

        if (Config::get_null_casts_as_any_type()) {
            return true;
        }

        // NullType is a sub-type of ScalarType. So it's affected by scalar_implicit_cast.
        if ($type->isScalar()) {
            if (Config::getValue('scalar_implicit_cast')) {
                return true;
            }
            $scalar_implicit_partial = Config::getValue('scalar_implicit_partial');
            // check if $type->getName() is in the list of permitted types $this->getName() can cast to.
            if (\count($scalar_implicit_partial) > 0 &&
                \in_array($type->getName(), $scalar_implicit_partial['null'] ?? [], true)) {
                return true;
            }
        }
        if ($type instanceof MixedType) {
            return true;
        }

        return false;
    }

    /**
     * @param bool $is_nullable (@phan-unused-param)
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withIsNullable(bool $is_nullable) : Type
    {
        return $this;
    }

    public function __toString() : string
    {
        return $this->name;
    }

    public function getIsPossiblyFalsey() : bool
    {
        return true;  // Null is always falsey.
    }

    public function getIsAlwaysFalsey() : bool
    {
        return true;  // Null is always falsey.
    }
}
