<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\AnnotatedUnionType;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;
use Phan\CodeBase;
use Phan\Config;

/**
 * This is generated from phpdoc such as array{field:int}
 */
final class ArrayShapeType extends ArrayType
{
    /** @phan-override */
    const NAME = 'array';

    /**
     * @var array<string|int,UnionType|AnnotatedUnionType>
     * Maps 0 or more field names to the corresponding types
     */
    private $field_types = [];

    /**
     * @var ?array<int,ArrayType>
     */
    private $as_generic_array_type_instances = null;

    /**
     * @var ?int
     */
    private $key_type = null;

    /**
     * @var ?UnionType
     */
    private $generic_array_element_union_type = null;

    /**
     * @param array<string|int,UnionType> $types
     * Maps 0 or more field names to the corresponding types
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass false
     */
    protected function __construct(array $types, bool $is_nullable)
    {
        // Could de-duplicate, but callers should be able to do that as well when converting to UnionType.
        // E.g. array<int|int> is int[].
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->field_types = $types;
    }

    /**
     * @return array<string|int,UnionType>
     * An array mapping field keys of this type to their union types.
     */
    public function getFieldTypes() : array
    {
        return $this->field_types;
    }

    /**
     * @param int|string $field_key
     */
    public function withoutField($field_key) : ArrayShapeType
    {
        $field_types = $this->field_types;
        if (!\array_key_exists($field_key, $field_types)) {
            return $this;
        }
        unset($field_types[$field_key]);
        return self::fromFieldTypes($field_types, $this->is_nullable);
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

        return self::fromFieldTypes(
            $this->field_types,
            $is_nullable
        );
    }

    /** @override */
    public function hasArrayShapeTypeInstances() : bool
    {
        return true;
    }

    /** @return array<int,Type> */
    private function computeGenericArrayTypeInstances() : array
    {
        $union_type_builder = new UnionTypeBuilder();
        foreach ($this->field_types as $key => $field_union_type) {
            foreach ($field_union_type->getTypeSet() as $type) {
                $union_type_builder->addType(GenericArrayType::fromElementType($type, $this->is_nullable, \is_string($key) ? GenericArrayType::KEY_STRING : GenericArrayType::KEY_INT));
            }
        }
        return $union_type_builder->getTypeSet();
    }

    public function getKeyType() : int
    {
        return $this->key_type ?? ($this->key_type = GenericArrayType::getKeyTypeForArrayLiteral($this->field_types));
    }

    public function genericArrayElementUnionType() : UnionType
    {
        return $this->generic_array_element_union_type ?? ($this->generic_array_element_union_type = UnionType::merge($this->field_types));
    }

    public function genericArrayElementType() : Type
    {
        // FIXME Deprecate genericArrayElementType
        return MixedType::instance(false);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof ArrayType) {
            if ($type instanceof GenericArrayType) {
                if (($this->getKeyType() & ($type->getKeyType() ?: GenericArrayType::KEY_MIXED)) === 0 && !Config::getValue('scalar_array_key_cast')) {
                    // Attempting to cast an int key to a string key (or vice versa) is normally invalid.
                    // However, the scalar_array_key_cast config would make any cast of array keys a valid cast.
                    return false;
                }
                return $this->genericArrayElementUnionType()->canCastToUnionType($type->genericArrayElementUnionType());
            } elseif ($type instanceof ArrayShapeType) {
                foreach ($type->field_types as $key => $field_type) {
                    $this_field_type = $this->field_types[$key] ?? null;
                    // Can't cast {a:int} to {a:int, other:string} if other is missing
                    if ($this_field_type === null) {
                        if ($field_type->getIsPossiblyUndefined()) {
                            // ... unless the other field is allowed to be undefined.
                            continue;
                        }
                        return false;
                    }
                    // can't cast {a:int} to {a:string} or {a:string=}
                    if (!$this_field_type->canCastToUnionType($field_type)) {
                        return false;
                    }
                }
                return true;
            }
            // array{key:T} can cast to array.
            return true;
        }

        if (\get_class($type) === IterableType::class) {
            // can cast to Iterable but not Traversable
            return true;
        }

        $d = \strtolower((string)$type);
        if ($d[0] == '\\') {
            $d = \substr($d, 1);
        }
        if ($d === 'callable') {
            if (\array_keys($this->field_types) !== [0, 1]) {
                return false;
            }
            // TODO: Check types of offsets 0 and 1
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @param array<string|int,UnionType> $field_types
     * @param bool $is_nullable
     * @return ArrayShapeType
     * TODO: deduplicate
     */
    public static function fromFieldTypes(
        array $field_types,
        bool $is_nullable
    ) : ArrayShapeType {
        // TODO: Investigate if caching makes this any more efficient?
        static $cache = [];

        $key_parts = [];
        foreach ($field_types as $key => $field_union_type) {
            $key_parts[$key] = $field_union_type->generateUniqueId();
        }
        if ($is_nullable) {
            $key_parts[] = '?';
        }
        $key = \json_encode($key_parts);

        return $cache[$key] ?? ($cache[$key] = new self($field_types, $is_nullable));
    }

    /**
     * Returns an empty array shape (for `array{}`)
     * @param bool $is_nullable
     * @return ArrayShapeType
     * @suppress PhanUnreferencedPublicMethod (TODO: Remove if we support empty array shapes and still don't use this)
     */
    public static function empty(
        bool $is_nullable = false
    ) : ArrayShapeType {
        // TODO: Investigate if caching makes this any more efficient?
        static $nullable_shape = null;
        static $nonnullable_shape = null;

        if ($is_nullable) {
            return $nullable_shape ?? ($nullable_shape = self::fromFieldTypes([], true));
        }
        return $nonnullable_shape ?? ($nonnullable_shape = self::fromFieldTypes([], false));
    }

    public function isGenericArray() : bool
    {
        return true;
    }

    public function __toString() : string
    {
        $parts = [];
        foreach ($this->field_types as $key => $value) {
            $value_repr = $value->__toString();
            if (\substr($value_repr, -1) === '=') {
                // convert {key:type=} to {key?:type} in representation.
                $parts[] = $key . '?:' . \substr($value_repr, 0, -1);
            } else {
                $parts[] = "$key:$value_repr";
            }
        }
        return ($this->is_nullable ? '?' : '') . 'array{' . \implode(',', $parts) . '}';
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
     *
     * TODO: Once Phan has full support for ArrayShapeType in the type system,
     * make asExpandedTypes return a UnionType with a single ArrayShapeType?
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
        return $this->memoize(__METHOD__, function () use ($code_base, $recursion_depth) {
            $result_fields = [];
            foreach ($this->field_types as $key => $union_type) {
                // UnionType already increments recursion_depth before calling asExpandedTypes on a subclass of Type,
                // and has a depth limit of 10.
                // Don't increase recursion_depth here, it's too easy to reach.
                $expanded_field_type = $union_type->asExpandedTypes($code_base, $recursion_depth);
                if ($union_type->getIsPossiblyUndefined()) {
                    // array{key?:string} should become array{key?:string}.
                    $expanded_field_type = $union_type->withIsPossiblyUndefined(true);
                }
                $result_fields[$key] = $expanded_field_type;
            }
            return ArrayShapeType::fromFieldTypes($result_fields, $this->is_nullable)->asUnionType();
        });
    }

    /**
     * @return GenericArrayType[]
     * @override
     */
    public function withFlattenedArrayShapeTypeInstances() : array
    {
        if (\is_array($this->as_generic_array_type_instances)) {
            return $this->as_generic_array_type_instances;
        }
        if (\count($this->field_types) === 0) {
            // there are 0 fields, so we know nothing about the field types (and there's no way to indicate an empty array yet)
            return $this->as_generic_array_type_instances = [ArrayType::instance($this->is_nullable)];
        }

        return $this->as_generic_array_type_instances = $this->computeGenericArrayTypeInstances();
    }

    public function asGenericArrayType(int $key_type) : Type
    {
        return GenericArrayType::fromElementType($this, false, $key_type);
    }

    /**
     * Computes the non-nullable union of two or more array shape types.
     *
     * E.g. array{0: string} + array{0:int,1:int} === array{0:int|string,1:int}
     * @param array<int,ArrayShapeType> $array_shape_types
     */
    public static function union(array $array_shape_types) : ArrayShapeType
    {
        if (\count($array_shape_types) === 0) {
            throw new \AssertionError('Unexpected union of 0 array shape types');
        }
        if (\count($array_shape_types) === 1) {
            return $array_shape_types[0];
        }
        $field_types = $array_shape_types[0]->field_types;
        unset($array_shape_types[0]);

        foreach ($array_shape_types as $type) {
            foreach ($type->field_types as $key => $union_type) {
                $old_union_type = $field_types[$key] ?? null;
                if (!isset($old_union_type)) {
                    $field_types[$key] = $union_type;
                    continue;
                }
                $field_types[$key] = $old_union_type->withUnionType($union_type);
            }
        }
        return self::fromFieldTypes($field_types, false);
    }

    /**
     * Computes the union of two array shape types.
     *
     * E.g. array{0: string} + array{0:stdClass,1:int} === array{0:string,1:int}
     */
    public static function combineWithPrecedence(ArrayShapeType $left, ArrayShapeType $right) : ArrayShapeType
    {
        return self::fromFieldTypes($left->field_types + $right->field_types, false);
    }

    /**
     * @override
     */
    public function shouldBeReplacedBySpecificTypes() : bool
    {
        return false;
    }
}
