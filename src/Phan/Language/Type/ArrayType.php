<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
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

    public function asNonTruthyType() : Type
    {
        // if (!$x) implies that $x is `[]` when $x is an array
        return ArrayShapeType::empty($this->is_nullable);
    }

    public function asNonFalseyType() : Type
    {
        // if (!$x) implies that $x is `[]` when $x is an array
        return NonEmptyGenericArrayType::fromElementType(
            MixedType::instance(false),
            false,
            GenericArrayType::KEY_MIXED
        );
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

    public function isCountable(CodeBase $unused_code_base) : bool
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
                return UnionType::of([$type], $union_type->getRealTypeSet());
            }
        }
        if ($result->isEmpty()) {
            return UnionType::of([ArrayShapeType::union($array_shape_types)], $union_type->getRealTypeSet());
        }
        foreach ($array_shape_types as $type) {
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result->addType($type_part);
            }
        }
        return UnionType::of($result->getTypeSet(), $union_type->getRealTypeSet());
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
    public static function combineArrayTypesOverriding(UnionType $left, UnionType $right) : UnionType
    {
        return UnionType::of(
            ArrayType::combineArrayTypeListsOverriding($left->getTypeSet(), $right->getTypeSet()),
            ArrayType::combineArrayTypeListsOverriding($left->getRealTypeSet(), $right->getRealTypeSet())
        );
    }

    /**
     * @param array<int,Type> $left_types
     * @param array<int,Type> $right_types
     * @return array<int,Type>
     */
    private static function combineArrayTypeListsOverriding(array $left_types, array $right_types) : array
    {
        $result = new UnionTypeBuilder();
        $left_array_shape_types = [];
        foreach ($left_types as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $left_array_shape_types[] = $type;
                } else {
                    // @phan-suppress-next-line PhanTypeMismatchArgument TODO support intersection types
                    $result->addType($type);
                }
            } elseif ($type instanceof ArrayType) {
                return [ArrayType::instance(false)];
            }
        }
        $right_array_shape_types = [];
        foreach ($right_types as $type) {
            if ($type instanceof GenericArrayInterface) {
                if ($type instanceof ArrayShapeType) {
                    $right_array_shape_types[] = $type;
                } else {
                    // @phan-suppress-next-line PhanTypeMismatchArgument TODO support intersection types
                    $result->addType($type);
                }
            } elseif ($type instanceof ArrayType) {
                return [$type];
            }
        }
        if ($result->isEmpty()) {
            if (\count($left_array_shape_types) === 0) {
                return $right_array_shape_types;
            }
            if (\count($right_array_shape_types) === 0) {
                return $left_array_shape_types;
            }
            // fields from the left take precedence (e.g. [0, false] + ['string'] becomes [0, false])
            return [ArrayShapeType::combineWithPrecedence(
                ArrayShapeType::union($left_array_shape_types),
                ArrayShapeType::union($right_array_shape_types)
            )];
        }
        foreach (\array_merge($left_array_shape_types, $right_array_shape_types) as $type) {
            foreach ($type->withFlattenedArrayShapeOrLiteralTypeInstances() as $type_part) {
                $result->addType($type_part);
            }
        }
        return $result->getTypeSet();
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
        return $result->getPHPDocUnionType();
    }

    protected function canCastToNonNullableType(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableType($type) || $type instanceof ArrayType || $type instanceof CallableDeclarationType;
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type) : bool
    {
        // CallableDeclarationType is not a native type, we check separately here
        return parent::canCastToNonNullableTypeWithoutConfig($type) || $type instanceof ArrayType || $type instanceof CallableDeclarationType;
    }

    public function canCastToDeclaredType(CodeBase $unused_code_base, Context $unused_context, Type $other) : bool
    {
        if ($other instanceof IterableType) {
            return true;
        }
        if ($this->isDefiniteNonCallableType()) {
            return false;
        }
        return $other instanceof CallableDeclarationType || $other instanceof CallableType;
    }

    /**
     * @return UnionType int|string for arrays
     */
    public function iterableKeyUnionType(CodeBase $unused_code_base) : UnionType
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

    // There are more specific checks in GenericArrayType and ArrayShapeType
    public function asCallableType() : ?Type
    {
        return CallableArrayType::instance(false);
    }

    public function asArrayType() : ?Type
    {
        return $this->withIsNullable(false);
    }

    /** @override of IterableType */
    public function asObjectType() : ?Type
    {
        return null;
    }

    public function canPossiblyCastToClass(CodeBase $unused_code_base, Type $unused_other) : bool
    {
        // arrays can't cast to object.
        return false;
    }
}
// Trigger the autoloader for GenericArrayType so that it won't be called
// before ArrayType.
// This won't pass if GenericArrayType is in the process of being instantiated.
\class_exists(GenericArrayType::class);
\class_exists(ArrayShapeType::class);
\class_exists(CallableArrayType::class);
