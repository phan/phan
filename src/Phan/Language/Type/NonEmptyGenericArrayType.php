<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Phan's representation for types such as `non-empty-array` and `non-empty-array<string,MyClass>`
 * @see GenericArrayType for representations of `string[]` and `array<int,bool>`
 * @see ArrayShapeType for representations of `array{key:MyClass}`
 * @see ArrayType for the representation of `array`
 * @phan-pure
 */
final class NonEmptyGenericArrayType extends GenericArrayType implements NonEmptyArrayInterface
{
    /**
     * @override
     * @return NonEmptyGenericArrayType
     * @phan-real-return NonEmptyGenericArrayType
     * (can't change signature type until minimum supported version is php 7.4)
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
                new NonEmptyGenericArrayType($type, $is_nullable, $key_type)
            );
        }

        return $map->offsetGet($type);
    }

    protected function canCastToNonNullableType(Type $type) : bool
    {
        if (!$type->isPossiblyTruthy()) {
            return false;
        }
        return parent::canCastToNonNullableType($type);
    }

    /** @override */
    public function isPossiblyFalsey() : bool
    {
        return $this->is_nullable;
    }

    /** @override */
    public function isAlwaysTruthy() : bool
    {
        return !$this->is_nullable;
    }

    /** @override */
    public function asNonFalseyType() : Type
    {
        return $this->withIsNullable(false);
    }

    public function __toString() : string
    {
        $string = $this->element_type->__toString();
        $string = 'non-empty-array<' . self::KEY_NAMES[$this->key_type] . ',' . $string . '>';

        if ($this->is_nullable) {
            $string = '?' . $string;
        }

        return $string;
    }

    /** @override */
    public function isDefinitelyNonEmptyArray() : bool
    {
        return true;
    }

    public function asAssociativeArrayType(bool $can_reduce_size) : ArrayType
    {
        if ($can_reduce_size) {
            return AssociativeArrayType::fromElementType(
                $this->element_type,
                $this->is_nullable,
                $this->key_type
            );
        }
        return NonEmptyAssociativeArrayType::fromElementType(
            $this->element_type,
            $this->is_nullable,
            $this->key_type
        );
    }

    /**
     * @return GenericArrayType
     * @phan-real-return GenericArrayType
     */
    public function asPossiblyEmptyArrayType() : ArrayType
    {
        return GenericArrayType::fromElementType(
            $this->element_type,
            $this->is_nullable,
            $this->key_type
        );
    }
}
