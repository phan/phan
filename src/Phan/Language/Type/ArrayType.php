<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of the type for `array`.
 * @see ArrayShapeType for the representation of `array{key:string}`
 * @see GenericArrayType for the representation of `MyClass[]`, `array<string,MyClass>`, etc.
 * @phan-pure
 */
class ArrayType extends IterableType
{
    /** @phan-override */
    public const NAME = 'array';

    public function asNonTruthyType(): Type
    {
        // if (!$x) implies that $x is `[]` when $x is an array
        return ArrayShapeType::empty($this->is_nullable);
    }

    public function asNonFalseyType(): Type
    {
        // if (!$x) implies that $x is `[]` when $x is an array
        return NonEmptyGenericArrayType::fromElementType(
            MixedType::instance(false),
            false,
            GenericArrayType::KEY_MIXED
        );
    }

    public function isPossiblyObject(): bool
    {
        return false;  // Overrides IterableType returning true
    }

    public function isArrayLike(): bool
    {
        return true;  // Overrides Type
    }

    /**
     * @unused-param $code_base
     * @override
     */
    public function isArrayOrArrayAccessSubType(CodeBase $code_base): bool
    {
        return true;  // Overrides Type
    }

    /**
     * @unused-param $code_base
     * @override
     */
    public function isCountable(CodeBase $code_base): bool
    {
        return true;  // Overrides Type
    }

    /**
     * @return UnionType with ArrayType subclass(es)
     * @suppress PhanUnreferencedPublicMethod may be used in the future or for plugins as array shape support improves.
     */
    public static function combineArrayTypesMerging(UnionType $union_type): UnionType
    {
        $result = [];
        $array_shape_types = [];
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $array_shape_types[] = $type;
                } else {
                    $result[] = $type;
                }
            } elseif ($type instanceof ArrayType) {
                return UnionType::of([$type], $union_type->getRealTypeSet());
            }
        }
        if (!$result) {
            return UnionType::of([ArrayShapeType::union($array_shape_types)], $union_type->getRealTypeSet());
        }
        foreach ($array_shape_types as $type) {
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result[] = $type_part;
            }
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        return UnionType::of($result, $union_type->getRealTypeSet());
    }

    /**
     * E.g. array{0:int} + array{0:string,1:float} becomes array{0:int,1:float}
     *
     * This also handles `$x['field'] = expr`.
     *
     * @param UnionType $left the left-hand side (e.g. of a `+` operator). Keys from these array shapes take precedence.
     * @param UnionType $right the right-hand side (e.g. of a `+` operator).
     * @return UnionType with ArrayType subclass(es)
     */
    public static function combineArrayTypesOverriding(UnionType $left, UnionType $right, bool $is_assignment = false): UnionType
    {
        // echo "Called " . __METHOD__ . " left={$left->getDebugRepresentation()} right={$right->getDebugRepresentation()} is_assignment=" . \json_encode($is_assignment) . "\n";
        return UnionType::of(
            ArrayType::combineArrayTypeListsOverriding($left->getTypeSet(), $right->getTypeSet(), $is_assignment, false),
            ArrayType::combineArrayTypeListsOverriding($left->getRealTypeSet(), $right->getRealTypeSet(), $is_assignment, true)
        );
    }

    /**
     * @param list<Type> $left_types The types being added to $right_types (types from array fields of these take precedence over $right_types)
     * @param list<Type> $right_types The type that is having $left_types get added to it.
     *
     * @param bool $is_assignment true if this is an assignment instead of a conditional.
     *                            This affects the field order of ArrayShapeType instances, among other things.
     * @param bool $is_real true if this is computing the real type set. If true, the resulting type is computed more conservatively, to avoid false positives for redundant/impossible condition detection.
     * @return list<Type>
     */
    private static function combineArrayTypeListsOverriding(array $left_types, array $right_types, bool $is_assignment, bool $is_real): array
    {
        if ($is_real && !$right_types) {
            return [];
        }
        $result = [];
        $left_array_shape_types = [];
        foreach ($left_types as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $left_array_shape_types[] = $type;
                } else {
                    $result[] = $type;
                }
            } elseif ($type instanceof ArrayType) {
                if ($is_real) {
                    return self::computeRealTypeSetFromArrayTypeLists($right_types, $is_assignment);
                }
                return [ArrayType::instance(false)];
            }
        }
        $right_array_shape_types = [];
        foreach ($right_types as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $right_array_shape_types[] = $type;
                } else {
                    $result[] = $type;
                }
            } elseif ($type instanceof ArrayType) {
                if ($is_assignment) {
                    $result[] = $type;
                } else {
                    if ($is_real) {
                        return self::computeRealTypeSetFromArrayTypeLists($right_types, $is_assignment);
                    }
                    return [$type];
                }
            } elseif ($is_real) {
                // TODO: More robust handling of non-arrays
                return [];
            }
        }
        if (\count($left_array_shape_types) === 0) {
            $combined_type_shapes = $right_array_shape_types;
        } elseif (\count($right_array_shape_types) === 0) {
            $combined_type_shapes = $left_array_shape_types;
        } else {
            // Fields from the left take precedence (e.g. [0, false] + ['string'] becomes [0, false])
            $left_union_type = ArrayShapeType::union($left_array_shape_types);
            $right_union_type = ArrayShapeType::union($right_array_shape_types);
            $combined_type_shapes = [ArrayShapeType::combineWithPrecedence($left_union_type, $right_union_type, $is_assignment)];
        }

        if (!$result) {
            return $combined_type_shapes;
        }
        // if ($is_real) {
        if ($left_array_shape_types) {
            foreach ($combined_type_shapes as $type) {
                $result[] = $type;
            }
        } else {
            foreach ($combined_type_shapes as $type) {
                foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                    $result[] = $type_part;
                }
            }
        }
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        return UnionType::getUniqueTypes($result);
        /*
        }
        foreach ($combined_type_shapes as $type) {
            $result[] = $type;
        }
        foreach ($left_array_shape_types as $type) {
            if ($is_assignment && !$is_real) {
                $result[] = $type;
                continue;
            }
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result[] = $type_part;
            }
        }
        foreach ($right_array_shape_types as $type) {
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result[] = $type_part;
            }
        }
        // (at)phan-suppress-next-line PhanPartialTypeMismatchArgument
        return UnionType::getUniqueTypes($result);
             */
    }

    /**
     * @param non-empty-list<Type> $right_types the original types being added to
     * @return list<ArrayType>
     */
    private static function computeRealTypeSetFromArrayTypeLists(array $right_types, bool $is_assignment): array
    {
        /* if (!$right_types) { return []; } */
        foreach ($right_types as $type) {
            if (!$type instanceof ArrayType && !($is_assignment && ($type instanceof NullType || $type instanceof VoidType))) {
                return [];
            }
        }
        static $array_type_set;
        return $array_type_set ?? ($array_type_set = UnionType::typeSetFromString('array'));
    }

    /**
     * E.g. string|array{0:T1|T2,1:float} + [0 => int] becomes string|array{0:int, 1:float}
     *
     * TODO: Remove any top-level native types that can't have offsets, e.g. IntType, null, etc.
     *
     * @param UnionType $left the left-hand side (e.g. of an isset check).
     * @param int|string|float|bool $field_dim_value (Ideally int|string)
     * @param UnionType $field_type
     * @return UnionType with ArrayType subclass(es)
     */
    public static function combineArrayShapeTypesWithField(UnionType $left, $field_dim_value, UnionType $field_type): UnionType
    {
        $type_set = [];
        $left_array_shape_types = [];
        foreach ($left->getTypeSet() as $type) {
            if ($type instanceof ArrayShapeType) {
                $left_array_shape_types[] = $type;
            } else {
                $type_set[] = $type;
            }
        }
        $type_set[] = ArrayShapeType::combineWithPrecedence(
            ArrayShapeType::fromFieldTypes([$field_dim_value => $field_type], false),
            // TODO: Add possibly_undefined annotations in union
            ArrayShapeType::union($left_array_shape_types)
        );
        $real_type_set = $left->hasRealTypeSet() ? self::computeRealTypeSetForArrayShapeTypeWithField($left, $field_dim_value, $field_type) : [];
        // TODO: Determine if the real type is an array
        return UnionType::of($type_set, $real_type_set);
    }

    /**
     * Precondition: $left has real types
     * @param UnionType $left the left-hand side (e.g. of an isset check).
     * @param int|string|float|bool $field_dim_value (Ideally int|string)
     * @param UnionType $field_type
     * @return list<ArrayType>
     */
    private static function computeRealTypeSetForArrayShapeTypeWithField(UnionType $left, $field_dim_value, UnionType $field_type): array
    {
        $has_non_array_shape = false;
        foreach ($left->getRealTypeSet() as $type) {
            if (!$type instanceof ArrayType || $type->isNullable()) {
                return [];
            }
            if (!$type instanceof ArrayShapeType) {
                $has_non_array_shape = true;
            }
        }
        if ($has_non_array_shape) {
            return [ArrayType::instance(false)];
        }
        return [
            ArrayShapeType::combineWithPrecedence(
                ArrayShapeType::fromFieldTypes([$field_dim_value => $field_type->asRealUnionType()], false),
                // @phan-suppress-next-line PhanTypeMismatchArgument this was asserted to be list<ArrayShapeType>
                ArrayShapeType::union($left->getRealTypeSet())
            )
        ];
    }

    protected function canCastToNonNullableType(Type $type): bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof ArrayType || $type instanceof CallableDeclarationType;
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableTypeWithoutConfig($type) || $type instanceof ArrayType || $type instanceof CallableDeclarationType;
    }

    /**
     * @override
     * @unused-param $code_base
     * @unused-param $context
     */
    public function canCastToDeclaredType(CodeBase $code_base, Context $context, Type $other): bool
    {
        if ($other instanceof IterableType || $other instanceof MixedType || $other instanceof TemplateType) {
            return true;
        }
        if ($this->isDefiniteNonCallableType()) {
            return false;
        }
        return $other instanceof CallableDeclarationType || $other instanceof CallableType;
    }

    /**
     * @override
     * @unused-param $code_base
     * @return UnionType int|string for arrays
     */
    public function iterableKeyUnionType(CodeBase $code_base): UnionType
    {
        // Reduce false positive partial type mismatch errors
        return UnionType::empty();
        /**
        static $result;
        if ($result === null) {
            $result = UnionType::fromFullyQualifiedString('int|string');
        }
        return $result;
         */
    }

    /**
     * Returns true if this contains a type that is definitely nullable or a non-object.
     * e.g. returns true false, array, int
     *      returns false for callable, object, iterable, T, etc.
     */
    public function isDefiniteNonObjectType(): bool
    {
        return true;
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags): bool
    {
        return parent::performComparison([], $scalar, $flags);
    }

    // There are more specific checks in GenericArrayType and ArrayShapeType
    public function asCallableType(): ?Type
    {
        return CallableArrayType::instance(false);
    }

    public function asArrayType(): ?Type
    {
        return $this->withIsNullable(false);
    }

    /** @override of IterableType */
    public function asObjectType(): ?Type
    {
        return null;
    }

    /**
     * @unused-param $code_base
     * @unused-param $other
     * @override
     */
    public function canPossiblyCastToClass(CodeBase $code_base, Type $other): bool
    {
        // arrays can't cast to object.
        return false;
    }

    /**
     * Returns the equivalent (possibly nullable) associative array type (or array shape type) for this type.
     *
     * TODO: Implement for ArrayShapeType (not currently calling it) with $can_reduce_size
     * @unused-param $can_reduce_size
     */
    public function asAssociativeArrayType(bool $can_reduce_size): ArrayType
    {
        return AssociativeArrayType::fromElementType(
            MixedType::instance(false),
            $this->is_nullable,
            GenericArrayType::KEY_MIXED
        );
    }

    /**
     * Convert ArrayTypes with integer-only keys to ListType.
     * Calling withFlattenedArrayShapeTypeInstances first is recommended.
     */
    public function convertIntegerKeyArrayToList(): ArrayType
    {
        // The base type has unknown keys. Do nothing.
        return $this;
    }

    public function weaklyOverlaps(Type $other): bool
    {
        // TODO: Could be stricter
        if ($other instanceof ScalarType) {
            if (!$other->isInBoolFamily()) {
                return false;
            }
        }
        return parent::weaklyOverlaps($other);
    }
}
// Trigger the autoloader for GenericArrayType so that it won't be called
// before ArrayType.
// This won't pass if GenericArrayType is in the process of being instantiated.
\class_exists(GenericArrayType::class);
\class_exists(ArrayShapeType::class);
\class_exists(CallableArrayType::class);
