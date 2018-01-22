<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\AST\UnionTypeVisitor;
use Phan\Language\Type;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\CodeBase;
use Phan\Config;

use ast\Node;

final class GenericArrayType extends ArrayType
{
    /** @phan-override */
    const NAME = 'array';

    // In PHP, array keys can be integers or strings. These constants describe all possible combinations of those key types.

    /**
     * No array keys.
     * Array types with this key type Similar to KEY_MIXED, but adding a key type will change the array to the new key
     * instead of staying as KEY_MIXED.
     */
    const KEY_EMPTY  = 0;  // No way to create this type yet.
    /** array keys are integers */
    const KEY_INT    = 1;
    /** array keys are strings */
    const KEY_STRING = 2;
    /** array keys are integers or strings. */
    const KEY_MIXED  = 3;  // i.e. KEY_INT|KEY_STRING

    const KEY_NAMES = [
        self::KEY_EMPTY  => 'empty',
        self::KEY_INT    => 'int',
        self::KEY_STRING => 'string',
        self::KEY_MIXED  => 'mixed',  // treated the same way as int|string
    ];

    /**
     * @var Type|null
     * The type of every value in this array
     */
    private $element_type = null;

    /**
     * @var int
     * Enum representing the type of every key in this array
     */
    private $key_type;

    /**
     * @param Type $type
     * The type of every element in this array
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @param int $key_type
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     */
    protected function __construct(Type $type, bool $is_nullable, int $key_type)
    {
        if ($key_type & ~3) {
            throw new \InvalidArgumentException("Invalid key_type $key_type");
        }
        parent::__construct('\\', self::NAME, [], false);
        $this->element_type = $type;
        $this->is_nullable = $is_nullable;
        $this->key_type = $key_type;
    }

    public function getKeyType() : int
    {
        return $this->key_type;
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
            $is_nullable,
            $this->key_type
        );
    }

    /**
     * @param int $key_type
     * The new key type.
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withKeyType(int $key_type) : Type
    {
        if ($key_type === $this->key_type) {
            return $this;
        }

        return GenericArrayType::fromElementType(
            $this->element_type,
            $this->is_nullable,
            $key_type
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
            if (!$this->genericArrayElementType()
                ->canCastToType($type->genericArrayElementType())) {
                return false;
            }
            if ((($this->key_type ?: self::KEY_MIXED) & ($type->key_type ?: self::KEY_MIXED)) === 0) {
                // Attempting to cast an int key to a string key (or vice versa) is normally invalid.
                // However, the scalar_array_key_cast config would make any cast of array keys a valid cast.
                return Config::getValue('scalar_array_key_cast');
            }
            return true;
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
     * @param int $key_type
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     *
     * @return GenericArrayType
     * Get a type representing an array of the given type
     */
    public static function fromElementType(
        Type $type,
        bool $is_nullable,
        int $key_type
    ) : GenericArrayType {
        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_maps = null;

        if ($canonical_object_maps === null) {
            $canonical_object_maps = [];
            for ($i = 0; $i < 8; $i++) {
                $canonical_object_maps[] = new \SplObjectStorage();
            }
        }
        $map_index = $key_type * 2 + ($is_nullable ? 1 : 0);

        $map = $canonical_object_maps[$map_index];

        if (!$map->contains($type)) {
            $map->attach(
                $type,
                new GenericArrayType($type, $is_nullable, $key_type)
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
        if ($this->key_type === self::KEY_MIXED) {
            // Disambiguation is needed for ?T[] and (?T)[] but not array<int,?T>
            if ($string[0] === '?') {
                $string = '(' . $string . ')';
            }
            $string = "{$string}[]";
        } else {
            $string = 'array<' . self::KEY_NAMES[$this->key_type] . ',' . $string . '>';
        }

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
                $clazz->getUnionType()->asGenericArrayTypes($this->key_type)
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
                    $alias_fqsen->asUnionType()->asGenericArrayTypes($this->key_type)
                );
            }
            return $recursive_union_type;
        });
        return clone($union_type);
    }

    public static function keyTypeFromUnionTypeKeys(UnionType $union_type) : int {
        $key_types = self::KEY_EMPTY;
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof GenericArrayType) {
                $key_types |= $type->key_type;
                continue;
                // TODO: support array shape as well?
            }
        }
        // int|string corresponds to KEY_MIXED (KEY_INT|KEY_STRING)
        // And if we're unable to find any types, return KEY_MIXED.
        return $key_types ?: self::KEY_MIXED;
    }

    /**
     * @return UnionType
     */
    public static function unionTypeForKeyType(int $key_type) : UnionType {
        switch ($key_type) {
        case self::KEY_INT: return IntType::instance(false)->asUnionType();
        case self::KEY_STRING: return StringType::instance(false)->asUnionType();
        default: return new UnionType();
        }
    }

    public static function keyTypeFromUnionTypeValues(UnionType $union_type) : int {
        $key_types = self::KEY_EMPTY;
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof StringType) {
                $key_types |= self::KEY_STRING;
            } elseif ($type instanceof IntType) {
                $key_types |= self::KEY_INT;
            } elseif ($type instanceof MixedType) {
                // Anything including a mixed type is a mixed type.
                return self::KEY_MIXED;
            } // skip invalid types.
        }
        // int|string corresponds to KEY_MIXED (KEY_INT|KEY_STRING)
        // And if we're unable to find any types, return KEY_MIXED.
        return $key_types ?: self::KEY_MIXED;
    }

    /**
     * @param array $array - The array keys are used for the final result.
     *
     * @return int
     * Corresponds to the type of the array keys of $array. This is a GenericArrayType::KEY_* constant (KEY_INT, KEY_STRING, or KEY_MIXED).
     */
    public static function getKeyTypeForArrayLiteral(array $array) : int {
        $key_type = GenericArrayType::KEY_EMPTY;
        foreach ($array as $key => $_) {
            $key_type |= (\is_string($key) ? GenericArrayType::KEY_STRING : GenericArrayType::KEY_INT);
        }
        return $key_type ?: GenericArrayType::KEY_MIXED;
    }

    /**
     * @return int
     * Corresponds to the type of the array keys of $array. This is a GenericArrayType::KEY_* constant (KEY_INT, KEY_STRING, or KEY_MIXED).
     */
    public static function getKeyTypeOfArrayNode(CodeBase $code_base, Context $context, Node $node, bool $should_catch_issue_exception = true) : int
    {
        $children = $node->children;
        if (!empty($children)
            && $children[0] instanceof Node
            && $children[0]->kind == \ast\AST_ARRAY_ELEM
        ) {
            $key_type_enum = GenericArrayType::KEY_EMPTY;
            // Check the first 5 (completely arbitrary) elements
            // and assume the rest are the same type
            for ($i=0; $i<5; $i++) {
                // Check to see if we're out of elements
                if (empty($children[$i])) {
                    break;
                }

                // Don't bother recursing more than one level to iterate over possible types.
                $key_node = $children[$i]->children['key'];
                if ($key_node instanceof Node) {
                    $key_type_enum |= self::keyTypeFromUnionTypeValues(UnionTypeVisitor::unionTypeFromNode(
                        $code_base,
                        $context,
                        $key_node,
                        $should_catch_issue_exception
                    ));
                } else if ($key_node !== null) {
                    if (\is_string($key_node)) {
                        $key_type_enum |= GenericArrayType::KEY_STRING;
                    } elseif (\is_int($key_node)) {
                        $key_type_enum |= GenericArrayType::KEY_INT;
                    }
                } else {
                    $key_type_enum |= GenericArrayType::KEY_INT;
                }
            }
            return $key_type_enum ?: GenericArrayType::KEY_MIXED;
        }
        return GenericArrayType::KEY_MIXED;
    }

}
