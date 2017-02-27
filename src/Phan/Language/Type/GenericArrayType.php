<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\CodeBase;

final class GenericArrayType extends ArrayType
{
    /** @phan-override */
    const NAME = 'array';

    /**
     * @var Type|null
     * The type of every element in this array
     */
    private $element_type = null;

    /**
     * @param Type $type
     * The type of every element in this array
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     */
    protected function __construct(Type $type, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], false);
        $this->element_type = $type;
        $this->is_nullable = $is_nullable;
    }

    /**
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withIsNullable(bool $is_nullable) : Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return GenericArrayType::fromElementType(
            $this->element_type,
            $is_nullable
        );
    }


    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof GenericArrayType) {
            return $this->genericArrayElementType()
                ->canCastToType($type->genericArrayElementType());
        }

        if ($type->isArrayLike()) {
            return true;
        }

        $d = \strtolower((string)$type);
        if ($d[0] == '\\') {
            $d = \substr($d, 1);
        }
        if ($d === 'callable') {
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @param Type $type
     * The element type for an array.
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return GenericArrayType
     * Get a type representing an array of the given type
     */
    public static function fromElementType(
        Type $type,
        bool $is_nullable
    ) : GenericArrayType {
        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_map_non_nullable = null;
        static $canonical_object_map_nullable = null;

        if (!$canonical_object_map_non_nullable) {
            $canonical_object_map_non_nullable = new \SplObjectStorage();
        }

        if (!$canonical_object_map_nullable) {
            $canonical_object_map_nullable = new \SplObjectStorage();
        }

        $map = $is_nullable
            ? $canonical_object_map_nullable
            : $canonical_object_map_non_nullable;

        if (!$map->contains($type)) {
            $map->attach(
                $type,
                new GenericArrayType($type, $is_nullable)
            );
        }

        return $map->offsetGet($type);
    }

    public function isGenericArray() : bool
    {
        return true;
    }

    /**
     * @return Type
     * A variation of this type that is not generic.
     * i.e. 'int[]' becomes 'int'.
     */
    public function genericArrayElementType() : Type
    {
        return $this->element_type;
    }

    public function __toString() : string
    {
        $string = (string)$this->element_type;
        if ($string[0] === '?') {
            $string = '(' . $string . ')';
        }
        $string = "{$string}[]";

        if ($this->getIsNullable()) {
            if ($string[0] === '?') {
                $string = "?($string)";
            } else {
                $string = '?' . $string;
            }
        }

        return $string;
    }

    /**
     * @param CodeBase
     * The code base to use in order to find super classes, etc.
     *
     * @param $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands class types to all inherited classes returning
     * a superset of this type.
     * @override
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ) : UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        \assert(
            $recursion_depth < 20,
            "Recursion has gotten out of hand"
        );

        $union_type = $this->memoize(__METHOD__, function () use ($code_base, $recursion_depth) {
            $union_type = $this->asUnionType();

            $class_fqsen = $this->genericArrayElementType()->asFQSEN();

            if (!($class_fqsen instanceof FullyQualifiedClassName)) {
                return $union_type;
            }

            \assert($class_fqsen instanceof FullyQualifiedClassName);

            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                return $union_type;
            }

            $clazz = $code_base->getClassByFQSEN($class_fqsen);

            $union_type->addUnionType(
                $clazz->getUnionType()->asGenericArrayTypes()
            );

            // Recurse up the tree to include all types
            $recursive_union_type = new UnionType();
            $representation = (string)$this;
            foreach ($union_type->getTypeSet() as $clazz_type) {
                if ((string)$clazz_type != $representation) {
                    $recursive_union_type->addUnionType(
                        $clazz_type->asExpandedTypes(
                            $code_base,
                            $recursion_depth + 1
                        )
                    );
                } else {
                    $recursive_union_type->addType($clazz_type);
                }
            }

            // Add in aliases
            // (If enable_class_alias_support is false, this will do nothing)
            $fqsen_aliases = $code_base->getClassAliasesByFQSEN($class_fqsen);
            foreach ($fqsen_aliases as $alias_fqsen_record) {
                $alias_fqsen = $alias_fqsen_record->alias_fqsen;
                $recursive_union_type->addUnionType(
                    $alias_fqsen->asUnionType()->asGenericArrayTypes()
                );
            }
            return $recursive_union_type;
        });
        return clone($union_type);
    }
}
