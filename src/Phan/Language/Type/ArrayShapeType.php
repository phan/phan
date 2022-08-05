<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Closure;
use Exception;
use Generator;
use Phan\CodeBase;
use Phan\Config;
use Phan\Debug\Frame;
use Phan\Exception\RecursionDepthException;
use Phan\Issue;
use Phan\Language\AnnotatedUnionType;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;
use RuntimeException;

/**
 * This is generated from phpdoc such as array{field:int}
 * @phan-pure
 * @phan-file-suppress PhanAccessReadOnlyProperty this is lazily initializing properties
 */
final class ArrayShapeType extends ArrayType implements GenericArrayInterface
{
    /** @phan-override */
    public const NAME = 'array';

    /**
     * @var array<string|int,UnionType|AnnotatedUnionType>
     * Maps 0 or more field names to the corresponding types
     */
    private $field_types = [];

    /**
     * This array shape converted to a list of 0 or more ArrayTypes.
     * This is lazily set.
     * @var ?list<ArrayType>
     */
    private $as_generic_array_type_instances = null;

    /**
     * @var ?int the key type enum value (constant from GenericArrayType)
     */
    private $key_type = null;

    /**
     * The union type of all possible value types of this array shape.
     * Lazily set.
     * @var ?UnionType
     */
    private $generic_array_element_union_type = null;

    /**
     * The list of all unique union types of values of this array shape.
     * E.g. `array{a:int,b:int,c:int|string}` will have two unique union types of values: `int`, and `int|string`
     * Lazily set.
     *
     * @var ?list<UnionType>
     */
    private $unique_value_union_types;

    /**
     * @param array<string|int,UnionType|AnnotatedUnionType> $types
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
    public function getFieldTypes(): array
    {
        return $this->field_types;
    }

    /**
     * Returns true if this has one or more optional or required fields
     * (i.e. this is not the type `array{}` or `?array{}`)
     */
    public function isNotEmptyArrayShape(): bool
    {
        return \count($this->field_types) !== 0;
    }

    /**
     * @override
     */
    public function isDefinitelyNonEmptyArray(): bool
    {
        foreach ($this->field_types as $field) {
            if (!$field->isPossiblyUndefined()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is this the union type `array{}` or `?array{}`?
     * @suppress PhanUnreferencedPublicMethod
     */
    public function isEmptyArrayShape(): bool
    {
        return \count($this->field_types) === 0;
    }

    /**
     * Returns an immutable array shape type instance without $field_key.
     *
     * @param int|string|float|bool $field_key
     */
    public function withoutField($field_key): ArrayShapeType
    {
        $field_types = $this->field_types;
        // This check is written this way to avoid https://github.com/phan/phan/issues/1831
        unset($field_types[$field_key]);
        if (\count($field_types) === \count($this->field_types)) {
            return $this;
        }
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
    public function withIsNullable(bool $is_nullable): Type
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
    public function hasArrayShapeOrLiteralTypeInstances(): bool
    {
        return true;
    }

    /** @override */
    public function hasArrayShapeTypeInstances(): bool
    {
        return true;
    }

    /**
     * @return list<ArrayType> the array shape transformed to remove literal keys and values.
     */
    private function computeGenericArrayTypeInstances(): array
    {
        if (\count($this->field_types) === 0) {
            // there are 0 fields, so we know nothing about the field types (and there's no way to indicate an empty array yet)
            return [ArrayType::instance($this->is_nullable)];
        }

        $union_type_builder = new UnionTypeBuilder();
        foreach ($this->field_types as $key => $field_union_type) {
            foreach ($field_union_type->getTypeSet() as $type) {
                $union_type_builder->addUnionType(
                    $type->asPHPDocUnionType()
                         ->withFlattenedArrayShapeOrLiteralTypeInstances()
                         ->asGenericArrayTypes(\is_string($key) ? GenericArrayType::KEY_STRING : GenericArrayType::KEY_INT)
                         ->withIsNullable($this->is_nullable)
                );
            }
        }
        // @phan-suppress-next-line PhanTypeMismatchReturn
        return $union_type_builder->getTypeSet();
    }

    /**
     * Returns the key type enum value (`GenericArrayType::KEY_*`) for the keys of this array shape.
     *
     * This is lazily computed.
     *
     * E.g. returns `GenericArrayType::KEY_STRING` for `array{key:\stdClass}`
     */
    public function getKeyType(): int
    {
        return $this->key_type ?? ($this->key_type = GenericArrayType::getKeyTypeForArrayLiteral($this->field_types));
    }

    /**
     * @unused-param $code_base
     * @phan-override
     */
    public function iterableKeyUnionType(CodeBase $code_base): UnionType
    {
        return $this->getKeyUnionType();
    }

    // TODO: Refactor other code calling unionTypeForKeyType to use this?
    /**
     * Gets the representation of the key type as a union type (without literals)
     *
     * E.g. returns `int` for `array{0:\stdClass}`
     */
    public function getKeyUnionType(): UnionType
    {
        if (\count($this->field_types) === 0) {
            return UnionType::empty();
        }
        return GenericArrayType::unionTypeForKeyType($this->getKeyType());
    }

    /**
     * @unused-param $code_base
     * @override
     */
    public function iterableValueUnionType(CodeBase $code_base): UnionType
    {
        return $this->genericArrayElementUnionType();
    }

    public function genericArrayElementUnionType(): UnionType
    {
        return $this->generic_array_element_union_type ?? ($this->generic_array_element_union_type = UnionType::merge($this->field_types));
    }

    /**
     * Returns true for `T` and `T[]` and `\MyClass<T>`, but not `\MyClass<\OtherClass>` or `false`
     */
    public function hasTemplateTypeRecursive(): bool
    {
        return $this->genericArrayElementUnionType()->hasTemplateTypeRecursive();
    }

    /**
     * @override
     * @param Type[] $target_type_set
     */
    public function canCastToAnyTypeInSet(array $target_type_set, CodeBase $code_base): bool
    {
        $element_union_types = null;
        foreach ($target_type_set as $target_type) {
            if ($target_type instanceof GenericArrayType) {
                if (!$this->canCastToGenericArrayKeys($target_type)) {
                    continue;
                }
                if ($element_union_types) {
                    '@phan-var UnionType $element_union_types';
                    $element_union_types = $element_union_types->withType($target_type->genericArrayElementType());
                } else {
                    $element_union_types = $target_type->genericArrayElementUnionType();
                }
                continue;
            }
            if ($this->canCastToType($target_type, $code_base)) {
                return true;
            }
        }
        if ($element_union_types) {
            return $this->canEachFieldTypeCastToExpectedUnionType($element_union_types, $code_base);
        }
        return false;
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ArrayType) {
            if ($type instanceof GenericArrayType) {
                return $this->canCastToGenericArrayKeys($type) &&
                    $this->canEachFieldTypeCastToExpectedUnionType($type->genericArrayElementUnionType(), $code_base);
            } elseif ($type instanceof ArrayShapeType) {
                foreach ($type->field_types as $key => $field_type) {
                    $this_field_type = $this->field_types[$key] ?? null;
                    // Can't cast {a:int} to {a:int, other:string} if other is missing
                    if ($this_field_type === null) {
                        if ($field_type->isPossiblyUndefined()) {
                            // ... unless the other field is allowed to be undefined.
                            continue;
                        }
                        return false;
                    }
                    // can't cast {a:int} to {a:string} or {a:string=}
                    if (!$this_field_type->canCastToUnionType($field_type, $code_base)) {
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
        if ($type instanceof GenericIterableType) {
            return $this->canCastToGenericIterableType($type, $code_base);
        }

        $d = \strtolower($type->__toString());
        if ($d[0] === '\\') {
            $d = \substr($d, 1);
        }
        if ($d === 'callable') {
            return !$this->isDefiniteNonCallableType($code_base);
        }

        return parent::canCastToNonNullableType($type, $code_base);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        if ($type instanceof ArrayType) {
            if ($type instanceof GenericArrayType) {
                // TODO: WithoutConfig here as well?
                return $this->canCastToGenericArrayKeys($type) &&
                    $this->canEachFieldTypeCastToExpectedUnionType($type->genericArrayElementUnionType(), $code_base);
            } elseif ($type instanceof ArrayShapeType) {
                foreach ($type->field_types as $key => $field_type) {
                    $this_field_type = $this->field_types[$key] ?? null;
                    // Can't cast {a:int} to {a:int, other:string} if other is missing
                    if ($this_field_type === null) {
                        if ($field_type->isPossiblyUndefined()) {
                            // ... unless the other field is allowed to be undefined.
                            continue;
                        }
                        return false;
                    }
                    // can't cast {a:int} to {a:string} or {a:string=}
                    if (!$this_field_type->canCastToUnionTypeWithoutConfig($field_type, $code_base)) {
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
        if ($type instanceof GenericIterableType) {
            return $this->canCastToGenericIterableType($type, $code_base);
        }

        $d = \strtolower($type->__toString());
        if ($d[0] === '\\') {
            $d = \substr($d, 1);
        }
        if ($d === 'callable') {
            return !$this->isDefiniteNonCallableType($code_base);
        }

        return parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
    }

    /**
     * Check if the keys of this array shape can cast to the keys of the generic array type $type
     */
    public function canCastToGenericArrayKeys(GenericArrayType $type, bool $ignore_config = false, bool $use_associative_heuristic = true): bool
    {
        if ($type instanceof ListType) {
            $i = 0;
            $has_possibly_undefined = false;
            foreach ($this->field_types as $k => $v) {
                if ($k !== $i++) {
                    return false;
                }
                if ($v->isPossiblyUndefined()) {
                    $has_possibly_undefined = true;
                } elseif ($has_possibly_undefined) {
                    return false;
                }
            }
        } else {
            if ($use_associative_heuristic && $type instanceof AssociativeArrayType) {
                if (!$this->canCastToAssociativeArray()) {
                    return false;
                }
            }
            if (($this->getKeyType() & ($type->getKeyType() ?: GenericArrayType::KEY_MIXED)) === 0 && ($ignore_config || !Config::getValue('scalar_array_key_cast'))) {
                // Attempting to cast an int key to a string key (or vice versa) is normally invalid.
                // However, the scalar_array_key_cast config would make any cast of array keys a valid cast.
                return false;
            }
        }
        if (!$this->field_types && $type->isDefinitelyNonEmptyArray()) {
            return false;
        }
        return true;
    }

    /**
     * True if this can cast to a list type, based on the keys
     * @internal
     */
    public function canCastToList(): bool
    {
        $i = 0;
        $has_possibly_undefined = false;
        foreach ($this->field_types as $k => $v) {
            if ($k !== $i++) {
                return false;
            }
            if ($v->isPossiblyUndefined()) {
                $has_possibly_undefined = true;
            } elseif ($has_possibly_undefined) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if this is empty or can't cast to a list.
     *
     * Phan allows array{0:string, 1?:string, 2?:string} to cast to associative arrays as well as lists.
     *
     * @internal
     */
    public function canCastToAssociativeArray(): bool
    {
        $i = 0;
        foreach ($this->field_types as $k => $v) {
            if ($k !== $i++ || $v->isPossiblyUndefined()) {
                return true;
            }
        }
        return \count($this->field_types) === 0;
    }

    private function canCastToGenericIterableType(GenericIterableType $iterable_type, CodeBase $code_base): bool
    {
        if (!$this->getKeyUnionType()->canCastToUnionType($iterable_type->getKeyUnionType(), $code_base)) {
            // TODO: Use the scalar_array_key_cast config
            return false;
        }
        return $this->canEachFieldTypeCastToExpectedUnionType($iterable_type->getElementUnionType(), $code_base);
    }

    /** @return list<UnionType> */
    private function getUniqueValueUnionTypes(): array
    {
        return $this->unique_value_union_types ?? ($this->unique_value_union_types = $this->calculateUniqueValueUnionTypes());
    }

    /** @return list<UnionType> */
    private function calculateUniqueValueUnionTypes(): array
    {
        $field_types = $this->field_types;
        $unique = [];
        foreach ($field_types as $value_union_type) {
            if ($value_union_type->isPossiblyUndefined()) {
                continue;
            }

            $value_union_type = $value_union_type->withIsPossiblyUndefined(false);
            $unique[$value_union_type->generateUniqueId()] = $value_union_type;
        }
        return \array_values($unique);
    }

    /**
     * This implements a type casting check for casting array shape values to element type of generic arrays.
     *
     * We reject casts of array{key:string,otherKey:int} to string[] because otherKey is there and incompatible
     *
     * We accept casts of array{key:string,otherKey:?int} to string[] because otherKey is possibly absent (to reduce
     */
    private function canEachFieldTypeCastToExpectedUnionType(UnionType $expected_type, CodeBase $code_base): bool
    {
        foreach ($this->getUniqueValueUnionTypes() as $value_union_type) {
            if (!$value_union_type->canCastToUnionType($expected_type, $code_base)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string|int,UnionType|AnnotatedUnionType> $field_types
     * @param bool $is_nullable
     * @return ArrayShapeType
     * TODO: deduplicate
     */
    public static function fromFieldTypes(
        array $field_types,
        bool $is_nullable
    ): ArrayShapeType {
        // TODO: Investigate if caching makes this any more efficient?
        static $cache = [];

        $key_parts = [];
        foreach ($field_types as $key => $field_union_type) {
            $key_parts[$key] = $field_union_type->generateUniqueId();
        }
        // NOTE: Use serialize instead of json_encode, because json_encode will fail for invalid utf-8
        $key = \serialize($key_parts) . ($is_nullable ? '?' : '');

        return $cache[$key] ?? ($cache[$key] = new self($field_types, $is_nullable));
    }

    /**
     * Returns an empty array shape (for `array{}`)
     * @param bool $is_nullable
     */
    public static function empty(
        bool $is_nullable = false
    ): ArrayShapeType {
        static $nullable_shape = null;
        static $nonnullable_shape = null;

        if ($is_nullable) {
            return $nullable_shape ?? ($nullable_shape = self::fromFieldTypes([], true));
        }
        return $nonnullable_shape ?? ($nonnullable_shape = self::fromFieldTypes([], false));
    }

    public function isGenericArray(): bool
    {
        return true;
    }

    /**
     * @internal - For use within ArrayShapeType
     */
    private const ESCAPE_CHARACTER_LOOKUP = [
        "\n" => '\\n',
        "\r" => '\\r',
        "\t" => '\\t',
        "\\" => '\\\\',
    ];

    /**
     * @internal - For use within ArrayShapeType
     */
    private const UNESCAPE_CHARACTER_LOOKUP = [
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\\\' => "\\",
    ];

    public function __toString(): string
    {
        $parts = [];
        foreach ($this->field_types as $key => $value) {
            if (\is_string($key)) {
                $key = self::escapeKey($key);
            }
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
     * Escape the key for display purposes
     */
    public static function escapeKey(string $key): string
    {
        return \preg_replace_callback(
            '([^-./^;$%*+_a-zA-Z0-9\x7f-\xff])',
            /**
             * @param array{0:string} $match
             */
            static function (array $match): string {
                $c = $match[0];
                return self::ESCAPE_CHARACTER_LOOKUP[$c] ?? \sprintf('\\x%02x', \ord($c));
            },
            $key
        );
    }

    /**
     * @param CodeBase $code_base
     * The code base to use in order to find super classes, etc.
     *
     * @param int $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands class types to all inherited classes returning
     * a superset of this type.
     *
     * @throws RuntimeException if the maximum recursion depth is exceeded
     * @override
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ): UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        if ($recursion_depth >= 20) {
            throw new RecursionDepthException("Recursion has gotten out of hand: " . Frame::getExpandedTypesDetails());
        }
        return $this->memoize(__METHOD__, function () use ($code_base, $recursion_depth): UnionType {
            $result_fields = [];
            foreach ($this->field_types as $key => $union_type) {
                // UnionType already increments recursion_depth before calling asExpandedTypes on a subclass of Type,
                // and has a depth limit of 10.
                // Don't increase recursion_depth here, it's too easy to reach.
                try {
                    $expanded_field_type = $union_type->asExpandedTypes($code_base, $recursion_depth);
                } catch (RecursionDepthException $_) {
                    $expanded_field_type = MixedType::instance(false)->asPHPDocUnionType();
                }
                if ($union_type->isPossiblyUndefined()) {
                    // array{key?:string} should become array{key?:string}.
                    $expanded_field_type = $union_type->withIsPossiblyUndefined(true);
                }
                $result_fields[$key] = $expanded_field_type;
            }
            // TODO: if the expanded types are different from the original type, maybe include both?
            return ArrayShapeType::fromFieldTypes($result_fields, $this->is_nullable)->asPHPDocUnionType();
        });
    }

    /**
     * @param CodeBase $code_base
     * The code base to use in order to find super classes, etc.
     *
     * @param int $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands class types to all inherited classes returning
     * a superset of this type.
     *
     * @throws RuntimeException if the maximum recursion depth is exceeded
     * @override
     */
    public function asExpandedTypesPreservingTemplate(
        CodeBase $code_base,
        int $recursion_depth = 0
    ): UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        if ($recursion_depth >= 20) {
            throw new RecursionDepthException("Recursion has gotten out of hand: " . Frame::getExpandedTypesDetails());
        }
        return $this->memoize(__METHOD__, function () use ($code_base, $recursion_depth): UnionType {
            $result_fields = [];
            foreach ($this->field_types as $key => $union_type) {
                // UnionType already increments recursion_depth before calling asExpandedTypesPreservingTemplate on a subclass of Type,
                // and has a depth limit of 10.
                // Don't increase recursion_depth here, it's too easy to reach.
                try {
                    $expanded_field_type = $union_type->asExpandedTypesPreservingTemplate($code_base, $recursion_depth);
                } catch (RecursionDepthException $_) {
                    $expanded_field_type = MixedType::instance(false)->asPHPDocUnionType();
                }
                if ($union_type->isPossiblyUndefined()) {
                    // array{key?:string} should become array{key?:string}.
                    $expanded_field_type = $union_type->withIsPossiblyUndefined(true);
                }
                $result_fields[$key] = $expanded_field_type;
            }
            return ArrayShapeType::fromFieldTypes($result_fields, $this->is_nullable)->asPHPDocUnionType();
        });
    }

    /**
     * @return list<ArrayType>
     * @override
     */
    public function withFlattenedArrayShapeOrLiteralTypeInstances(): array
    {
        $instances = $this->as_generic_array_type_instances;
        if (\is_array($instances)) {
            return $instances;
        }
        return $this->as_generic_array_type_instances = $this->computeGenericArrayTypeInstances();
    }

    /**
     * @return list<ArrayType>
     * @override
     */
    public function withFlattenedTopLevelArrayShapeTypeInstances(): array
    {
        if (\count($this->field_types) === 0) {
            // there are 0 fields, so we know nothing about the field types (and there's no way to indicate an empty array yet)
            return [ArrayType::instance($this->is_nullable)];
        }

        $union_type_builder = new UnionTypeBuilder();
        foreach ($this->field_types as $key => $field_union_type) {
            foreach ($field_union_type->getTypeSet() as $type) {
                $union_type_builder->addUnionType(
                    $type->asPHPDocUnionType()
                         ->withFlattenedArrayShapeOrLiteralTypeInstances()
                         ->asGenericArrayTypes(\is_string($key) ? GenericArrayType::KEY_STRING : GenericArrayType::KEY_INT)
                         ->withIsNullable($this->is_nullable)
                );
            }
        }
        // @phan-suppress-next-line PhanTypeMismatchReturn
        return $union_type_builder->getTypeSet();
    }

    public function asGenericArrayType(int $key_type): Type
    {
        return GenericArrayType::fromElementType($this, false, $key_type);
    }

    /**
     * Computes the non-nullable union of two or more array shape types.
     *
     * E.g. array{0: string} + array{0:int,1:int} === array{0:int|string,1:int}
     * @param list<ArrayShapeType> $array_shape_types
     */
    public static function union(array $array_shape_types): ArrayShapeType
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
                if ($old_union_type === null) {
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
     *
     * @param bool $is_assignment - If true, this is computing the effect of assigning each field in $left to an array with previous type $right, keeping array key order.
     */
    public static function combineWithPrecedence(ArrayShapeType $left, ArrayShapeType $right, bool $is_assignment = false): ArrayShapeType
    {
        // echo "Called combineWithPrecedence left=$left right=$right is_assignment=" . json_encode($is_assignment) . "\n";
        if ($is_assignment) {
            // Not using $left->field_types + $right->field_types because that would put the array keys from $added before the array keys from $existing when iterating/displaying types.
            $combination = $right->field_types;
            foreach ($left->field_types as $i => $type) {
                $combination[$i] = $type;
            }
        } else {
            $combination = $left->field_types + $right->field_types;
        }
        return self::fromFieldTypes($combination, false);
    }

    /**
     * @phan-override
     */
    public function shouldBeReplacedBySpecificTypes(): bool
    {
        return false;
    }

    /**
     * @return bool true if there is guaranteed to be at least one property
     * @phan-override
     */
    public function isAlwaysTruthy(): bool
    {
        if ($this->is_nullable) {
            return false;
        }
        foreach ($this->field_types as $field) {
            if (!$field->isPossiblyUndefined()) {
                return true;
            }
        }
        return false;
    }

    public function isAlwaysFalsey(): bool
    {
        return \count($this->field_types) === 0;
    }

    public function isPossiblyTruthy(): bool
    {
        return \count($this->field_types) > 0;
    }

    public function isPossiblyFalsey(): bool
    {
        return !$this->isAlwaysTruthy();
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, array, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType(CodeBase $code_base): bool
    {
        if (\array_keys($this->field_types) !== [0, 1]) {
            return true;
        }
        if (!$this->field_types[0]->canCastToUnionType(UnionType::fromFullyQualifiedPHPDocString('string|object'), $code_base)) {
            // First field of callable array should be a string or object. (the expression or class)
            return true;
        }
        if (!$this->field_types[1]->canCastToUnionType(StringType::instance(false)->asPHPDocUnionType(), $code_base)) {
            // Second field of callable array should be the method name.
            return true;
        }
        return false;
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     * A map from template type identifiers to concrete types
     *
     * @return UnionType
     * This UnionType with any template types contained herein
     * mapped to concrete types defined in the given map.
     *
     * Overridden in subclasses
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ): UnionType {
        $field_types = $this->field_types;
        foreach ($field_types as $i => $type) {
            $new_type = $type->withTemplateParameterTypeMap($template_parameter_type_map);
            if ($new_type !== $type) {
                $field_types[$i] = $new_type;
            }
        }
        if ($field_types === $this->field_types) {
            return $this->asPHPDocUnionType();
        }
        return self::fromFieldTypes($field_types, $this->is_nullable)->asPHPDocUnionType();
    }

    /**
     * If this generic array type in a parameter declaration has template types, get the closure to extract the real types for that template type from argument union types
     *
     * @param CodeBase $code_base
     * @return ?Closure(UnionType, Context):UnionType
     */
    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type): ?Closure
    {
        $closure = null;
        foreach ($this->field_types as $key => $type) {
            $field_closure = $type->getTemplateTypeExtractorClosure($code_base, $template_type);
            if (!$field_closure) {
                continue;
            }
            $closure = TemplateType::combineParameterClosures(
                $closure,
                static function (UnionType $union_type, Context $context) use ($key, $field_closure): UnionType {
                    $result = UnionType::empty();
                    foreach ($union_type->getTypeSet() as $type) {
                        if (!($type instanceof ArrayShapeType)) {
                            continue;
                        }
                        $field_type = $type->field_types[$key] ?? null;
                        if ($field_type) {
                            $result = $result->withUnionType($field_closure($field_type, $context));
                        }
                    }
                    return $result;
                }
            );
        }
        return $closure;
    }

    /**
     * If all types in this array shape can be converted to a single PHP value,
     * and all fields are required, return the array shape represented by that.
     *
     * Otherwise, return null
     *
     * @return ?array<mixed,?string|?int|?float|?bool|?array>
     */
    public function asArrayLiteralOrNull()
    {
        $result = [];
        foreach ($this->field_types as $key => $field_type) {
            $field_value = $field_type->asValueOrNullOrSelf();
            if (\is_object($field_value)) {
                return null;
            }
            $result[$key] = $field_value;
        }
        return $result;
    }

    /**
     * Returns the function interface this references
     * @unused-param $warn
     */
    public function asFunctionInterfaceOrNull(CodeBase $code_base, Context $context, bool $warn = true): ?FunctionInterface
    {
        if (\count($this->field_types) !== 2) {
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::TypeInvalidCallableArraySize,
                $context->getLineNumberStart(),
                \count($this->field_types)
            );
            return null;
        }
        $i = 0;
        foreach ($this->field_types as $key => $_) {
            if ($key !== $i) {
                // TODO: Be more consistent about emitting issues in Type->asFunctionInterfaceOrNull and its subclasses (e.g. if missing __invoke)
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    Issue::TypeInvalidCallableArrayKey,
                    $context->getLineNumberStart(),
                    $i
                );
                return null;
            }
            $i++;
        }
        $method_name = $this->field_types[1]->asSingleScalarValueOrNull();
        if (!\is_string($method_name)) {
            return null;
        }
        foreach ($this->field_types[0]->getUniqueFlattenedTypeSet() as $type) {
            $class = null;
            if ($type instanceof LiteralStringType) {
                try {
                    $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($type->getValue());
                    if (!$code_base->hasClassWithFQSEN($fqsen)) {
                        continue;
                    }
                } catch (Exception $_) {
                    continue;
                }
            } elseif ($type->isObjectWithKnownFQSEN()) {
                $fqsen = $type->asFQSEN();
                if (!$fqsen instanceof FullyQualifiedClassName) {
                    continue;
                }
            } else {
                continue;
            }
            if ($code_base->hasClassWithFQSEN($fqsen)) {
                $class = $code_base->getClassByFQSEN($fqsen);
                if ($class->hasMethodWithName($code_base, $method_name, true)) {
                    return $class->getMethodByName($code_base, $method_name);
                }
            }
        }

        return null;
    }

    /**
     * @return Generator<mixed,Type> (void => $inner_type)
     */
    public function getReferencedClasses(): Generator
    {
        // Whether union types or types have been seen already for this ArrayShapeType
        $seen = [];
        foreach ($this->field_types as $type) {
            $id = \spl_object_id($type);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            foreach ($type->getReferencedClasses() as $inner_type) {
                $id = \spl_object_id($inner_type);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                yield $inner_type;
            }
        }
    }

    /**
     * Convert an escaped key to an unescaped key
     */
    public static function unescapeKey(string $escaped_key): string
    {
        return \preg_replace_callback(
            '/\\\\(?:[nrt\\\\]|x[0-9a-fA-F]{2})/',
            /** @param array{0:string} $matches */
            static function (array $matches): string {
                $x = $matches[0];
                if (\strlen($x) === 2) {
                    // Parses \\, \n, \t, and \r
                    return self::UNESCAPE_CHARACTER_LOOKUP[$x];
                }
                // convert 2 hex bytes to a single character
                // @phan-suppress-next-line PhanPossiblyFalseTypeArgumentInternal, PhanPartialTypeMismatchArgumentInternal
                return \chr(\hexdec(\substr($x, 2)));
            },
            $escaped_key
        );
    }

    /**
     * Returns the corresponding type that would be used in a signature
     * @override
     */
    public function asSignatureType(): Type
    {
        return ArrayType::instance($this->is_nullable);
    }

    public function withStaticResolvedInContext(Context $context): Type
    {
        $did_change = false;
        $new_field_types = $this->field_types;
        foreach ($new_field_types as $i => $field_type) {
            $new_field_type = $field_type->withStaticResolvedInContext($context);
            if ($new_field_type !== $field_type) {
                $did_change = true;
                $new_field_types[$i] = $new_field_type;
            }
        }
        if (!$did_change) {
            return $this;
        }
        return self::fromFieldTypes($new_field_types, $this->is_nullable);
    }

    /**
     * Returns a type where all referenced union types (e.g. in generic arrays) have real type sets removed.
     */
    public function withErasedUnionTypes(): Type
    {
        return $this->memoize(__METHOD__, function (): ArrayShapeType {
            $new_field_types = $this->field_types;
            foreach ($this->field_types as $offset => $union_type) {
                $new_field_types[$offset] = $union_type->eraseRealTypeSetRecursively();
            }
            if ($new_field_types === $this->field_types) {
                return $this;
            }
            return self::fromFieldTypes($new_field_types, $this->is_nullable);
        });
    }

    public function asCallableType(CodeBase $code_base): ?Type
    {
        if ($this->isDefiniteNonCallableType($code_base)) {
            return null;
        }
        return $this->withIsNullable(false);
    }

    public function asNonFalseyType(): Type
    {
        if ($this->field_types) {
            // No simple way to handle `array{a?:b}` - just make it non-nullable
            return $this->withIsNullable(false);
        }
        return NonEmptyGenericArrayType::fromElementType(
            MixedType::instance(false),
            false,
            GenericArrayType::KEY_MIXED
        );
    }

    /**
     * @override
     * @unused-param $can_reduce_size
     */
    public function asAssociativeArrayType(bool $can_reduce_size): ArrayType
    {
        return $this;
    }

    /**
     * @override
     */
    public function castToListTypes(): UnionType
    {
        if ($this->canCastToList()) {
            // NOTE: This is a bad approximation for array{0:T1, 1?:T2, 2?:T3}
            return $this->asPHPDocUnionType();
        }
        // If this has at least one string type the condition array_is_list does not hold
        foreach ($this->field_types as $k => $v) {
            if (\is_string($k) && !$v->isPossiblyUndefined()) {
                return UnionType::empty();
            }
        }
        return $this->genericArrayElementUnionType()->asListTypes();
    }

    public function getTypesRecursively(): Generator
    {
        yield $this;
        foreach ($this->field_types as $type) {
            yield from $type->getTypesRecursively();
        }
    }

    public function isSubtypeOf(Type $type, CodeBase $code_base): bool
    {
        // Check to see if we have an exact object match
        if ($this === $type) {
            return true;
        }
        if (\in_array($type, $this->asExpandedTypes($code_base)->getTypeSet(), true)) {
            return true;
        }

        $other_is_nullable = $type->isNullable();
        // A nullable type is not a subtype of a non-nullable type
        if ($this->is_nullable && !$other_is_nullable) {
            return false;
        }
        if ($type instanceof ArrayShapeType) {
            foreach ($type->field_types as $field_name => $field_type) {
                $field_type_of_this = $this->field_types[$field_name] ?? null;
                if (!$field_type_of_this) {
                    return false;
                }
                if (!$field_type_of_this->isStrictSubtypeOf($code_base, $field_type)) {
                    return false;
                }
            }
            return true;
        }
        if ($type instanceof GenericArrayType) {
            // perform regular checks but allow array{0:string} to cast to associative-array (excluding associative-array<string, ...>)
            if (!$this->canCastToGenericArrayKeys($type, false, false)) {
                return false;
            }
            $element_type = $type->iterableValueUnionType();
            foreach ($this->field_types as $field_type) {
                if (!$field_type->withIsPossiblyUndefined(false)->canCastToUnionType($element_type, $code_base)) {
                    return false;
                }
            }
            if ($type->isDefinitelyNonEmptyArray() && !$this->isDefinitelyNonEmptyArray()) {
                return false;
            }
            return true;
        }

        if ($type instanceof MixedType) {
            // e.g. ?int is a subtype of mixed, but ?int is not a subtype of non-empty-mixed/non-null-mixed
            // (check isNullable first)
            // This is not NullType; it has to be truthy to cast to non-empty-mixed.
            return \get_class($type) !== NonEmptyMixedType::class || $this->isPossiblyTruthy();
        }

        // Get a non-null version of the type we're comparing
        // against.
        if ($other_is_nullable) {
            $type = $type->withIsNullable(false);

            // Check one more time to see if the types are equal
            if ($this === $type) {
                return true;
            }
        }

        // Test to see if we are a subtype of the non-nullable version
        // of the target type.
        return $this->isSubtypeOfNonNullableType($type, $code_base);
    }
}
