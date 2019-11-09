<?php declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Phan's representation for types such as `non-empty-associative-array` and `non-empty-associative-array<string, MyClass>`
 * @see GenericArrayType for representations of `string[]` and `array<int,bool>`
 * @see ArrayShapeType for representations of `array{key:MyClass}`
 * @see ArrayType for the representation of `array`
 * @phan-pure
 */
final class NonEmptyAssociativeArrayType extends AssociativeArrayType implements NonEmptyArrayInterface
{
    /**
     * @override
     */
    public static function fromElementType(
        Type $type,
        bool $is_nullable,
        int $key_type = GenericArrayType::KEY_INT
    ) : GenericArrayType {
        if ($key_type === GenericArrayType::KEY_STRING) {
            return NonEmptyGenericArrayType::fromElementType($type, $is_nullable, $key_type);
        }
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
                new NonEmptyAssociativeArrayType($type, $is_nullable, $key_type)
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
        if ($this->key_type === GenericArrayType::KEY_MIXED) {
            $key_details = '';
        } else {
            $key_details = self::KEY_NAMES[$this->key_type] . ',';
        }
        return ($this->is_nullable ? '?' : '') .
            'non-empty-associative-array<' . $key_details . $this->element_type->__toString() . '>';
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
        return $this;
    }

    /**
     * @return ListType
     * @phan-real-return AssociativeArrayType
     */
    public function asPossiblyEmptyArrayType() : ArrayType
    {
        return AssociativeArrayType::fromElementType(
            $this->element_type,
            $this->is_nullable,
            $this->key_type
        );
    }
}
