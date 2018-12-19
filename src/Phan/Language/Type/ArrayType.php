<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

/**
 * Phan's representation of the type for `array`.
 * @see ArrayShapeType for the representation of `array{key:string}`
 * @see GenericArrayType for the representation of `MyClass[]`, `array<string,MyClass>`, etc.
 */
class ArrayType extends IterableType
{
    /** @phan-override */
    const NAME = 'array';

    public function getIsAlwaysTruthy() : bool
    {
        return false;
    }

    public function asNonTruthyType() : Type
    {
        // if (!$x) implies that $x is `[]` when $x is an array
        return ArrayShapeType::empty($this->is_nullable);
    }

    public function isPossiblyObject() : bool
    {
        return false;  // Overrides IterableType returning true
    }

    public function isArrayLike() : bool
    {
        return true;  // Overrides Type
    }

    public function isArrayOrArrayAccessSubType(CodeBase $unused_code_base) : bool
    {
        return true;  // Overrides Type
    }

    /**
     * @return UnionType with ArrayType subclass(es)
     * @suppress PhanUnreferencedPublicMethod may be used in the future or for plugins as array shape support improves.
     */
    public static function combineArrayTypesMerging(UnionType $union_type) : UnionType
    {
        $result = new UnionTypeBuilder();
        $array_shape_types = [];
        foreach ($union_type->getTypeSet() as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $array_shape_types[] = $type;
                } else {
                    // @phan-suppress-next-line PhanTypeMismatchArgument TODO support intersection types
                    $result->addType($type);
                }
            } elseif ($type instanceof ArrayType) {
                return $type->asUnionType();
            }
        }
        if ($result->isEmpty()) {
            return ArrayShapeType::union($array_shape_types)->asUnionType();
        }
        foreach ($array_shape_types as $type) {
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result->addType($type_part);
            }
        }
        return $result->getUnionType();
    }

    /**
     * E.g. array{0:int} + array{0:string,1:float} becomes array{0:int,1:float}
     *
     * @param UnionType $left the left-hand side (e.g. of a `+` operator). Keys from these array shapes take precedence.
     * @param UnionType $right the right-hand side (e.g. of a `+` operator).
     * @return UnionType with ArrayType subclass(es)
     */
    public static function combineArrayTypesOverriding(UnionType $left, UnionType $right) : UnionType
    {
        $result = new UnionTypeBuilder();
        $left_array_shape_types = [];
        foreach ($left->getTypeSet() as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $left_array_shape_types[] = $type;
                } else {
                    // @phan-suppress-next-line PhanTypeMismatchArgument TODO support intersection types
                    $result->addType($type);
                }
            } elseif ($type instanceof ArrayType) {
                return $type->asUnionType();
            }
        }
        $right_array_shape_types = [];
        foreach ($right->getTypeSet() as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $right_array_shape_types[] = $type;
                } else {
                    // @phan-suppress-next-line PhanTypeMismatchArgument TODO support intersection types
                    $result->addType($type);
                }
            } elseif ($type instanceof ArrayType) {
                return $type->asUnionType();
            }
        }
        if ($result->isEmpty()) {
            if (\count($left_array_shape_types) === 0) {
                return ArrayShapeType::union($right_array_shape_types)->asUnionType();
            }
            if (\count($right_array_shape_types) === 0) {
                return ArrayShapeType::union($left_array_shape_types)->asUnionType();
            }
            // fields from the left take precedence (e.g. [0, false] + ['string'] becomes [0, false])
            return ArrayShapeType::combineWithPrecedence(
                ArrayShapeType::union($left_array_shape_types),
                ArrayShapeType::union($right_array_shape_types)
            )->asUnionType();
        }
        foreach (\array_merge($left_array_shape_types, $right_array_shape_types) as $type) {
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result->addType($type_part);
            }
        }
        return $result->getUnionType();
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
    public static function combineArrayShapeTypesWithField(UnionType $left, $field_dim_value, UnionType $field_type) : UnionType
    {
        $result = new UnionTypeBuilder();
        $left_array_shape_types = [];
        foreach ($left->getTypeSet() as $type) {
            if ($type instanceof ArrayShapeType) {
                $left_array_shape_types[] = $type;
            } else {
                $result->addType($type);
            }
        }
        $result->addType(ArrayShapeType::combineWithPrecedence(
            ArrayShapeType::fromFieldTypes([$field_dim_value => $field_type], false),
            // TODO: Add possibly_undefined annotations in union
            ArrayShapeType::union($left_array_shape_types)
        ));
        return $result->getUnionType();
    }

    /**
     * Overridden in subclasses
     *
     * @param int $key_type
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     *
     * @return Type
     * Get a new type which is the generic array version of
     * this type. For instance, 'int[]' will produce 'int[][]'.
     *
     * As a special case to reduce false positives, 'array' (with no known types) will produce 'array'
     */
    public function asGenericArrayType(int $key_type) : Type
    {
        return GenericArrayType::fromElementType($this, false, $key_type);
    }

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof ArrayType || $type instanceof CallableDeclarationType;
    }

    /**
     * @return UnionType int|string for arrays
     */
    public function iterableKeyUnionType(CodeBase $unused_code_base)
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
    public function isDefiniteNonObjectType() : bool
    {
        return true;
    }

    /**
     * Check if this type can satisfy a comparison (<, <=, >, >=)
     * @param int|string|float|bool|null $scalar
     * @param int $flags (e.g. \ast\flags\BINARY_IS_SMALLER)
     * @internal
     */
    public function canSatisfyComparison($scalar, int $flags) : bool
    {
        return parent::performComparison([], $scalar, $flags);
    }
}
// Trigger the autoloader for GenericArrayType so that it won't be called
// before ArrayType.
// This won't pass if GenericArrayType is in the process of being instantiated.
\class_exists(GenericArrayType::class);
\class_exists(ArrayShapeType::class);
