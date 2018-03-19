<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

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
        // There's no EmptyArrayType, so return $this
        return $this;
    }

    public function isPossiblyObject() : bool
    {
        return false;  // Overrides IterableType returning true
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
            if ($type->isGenericArray()) {
                if ($type instanceof ArrayShapeType) {
                    $array_shape_types[] = $type;
                } else {
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
            foreach ($type->withFlattenedArrayShapeTypeInstances() as $type_part) {
                $result->addType($type_part);
            }
        }
        return $result->getUnionType();
    }

    /**
     * E.g. array{0:int} + array{0:string,1:float} becomes array{0:int,1:float}
     *
     * @param UnionType $left the left hand side (e.g. of a `+` operator). Keys from these array shapes take precedence.
     * @param UnionType $right the right hand side (e.g. of a `+` operator).
     * @return UnionType with ArrayType subclass(es)
     */
    public static function combineArrayTypesOverriding(UnionType $left, UnionType $right) : UnionType
    {
        $result = new UnionTypeBuilder();
        $left_array_shape_types = [];
        foreach ($left->getTypeSet() as $type) {
            if ($type->isGenericArray()) {
                if ($type instanceof ArrayShapeType) {
                    $left_array_shape_types[] = $type;
                } else {
                    $result->addType($type);
                }
            } elseif ($type instanceof ArrayType) {
                return $type->asUnionType();
            }
        }
        $right_array_shape_types = [];
        foreach ($right->getTypeSet() as $type) {
            if ($type->isGenericArray()) {
                if ($type instanceof ArrayShapeType) {
                    $right_array_shape_types[] = $type;
                } else {
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
            foreach ($type->withFlattenedArrayShapeTypeInstances() as $type_part) {
                $result->addType($type_part);
            }
        }
        return $result->getUnionType();
    }

    /**
     * Overridden in subclasses
     *
     * @param int $key_type @phan-unused-param (TODO: Use?)
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
        // TODO: Allow one more level of nesting? E.g. array->array[], but array[]->array[]
        return ArrayType::instance(false);
    }

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof CallableDeclarationType;
    }
}
// Trigger the autoloader for GenericArrayType so that it won't be called
// before ArrayType.
// This won't pass if GenericArrayType is in the process of being instantiated.
\class_exists(GenericArrayType::class);
\class_exists(ArrayShapeType::class);
