<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Phan's representation for types such as `list` and `list<MyClass>`
 * @see GenericArrayType for representations of `string[]` and `array<int,bool>`
 * @see ArrayShapeType for representations of `array{key:MyClass}`
 * @see ArrayType for the representation of `array`
 * @phan-pure
 */
class ListType extends GenericArrayType
{
    protected function __construct(Type $type, bool $is_nullable)
    {
        parent::__construct($type, $is_nullable, GenericArrayType::KEY_INT);
    }

    public static function fromElementType(
        Type $type,
        bool $is_nullable,
        int $unused_key_type = GenericArrayType::KEY_INT
    ): GenericArrayType {
        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_maps = null;

        if ($canonical_object_maps === null) {
            $canonical_object_maps = [];
            for ($i = 0; $i < 2; $i++) {
                $canonical_object_maps[] = new \SplObjectStorage();
            }
        }
        $map_index = ($is_nullable ? 1 : 0);

        $map = $canonical_object_maps[$map_index];

        if (!$map->contains($type)) {
            $map->attach(
                $type,
                new ListType($type, $is_nullable)
            );
        }

        return $map->offsetGet($type);
    }
    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type): bool
    {
        return $this->canCastToTypeCommon($type) &&
            parent::canCastToNonNullableType($type);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type): bool
    {
        return $this->canCastToTypeCommon($type) &&
            parent::canCastToNonNullableTypeWithoutConfig($type);
    }

    private function canCastToTypeCommon(Type $type): bool
    {
        if (!$type->isPossiblyTruthy()) {
            return false;
        }
        if ($type instanceof ArrayShapeType) {
            if (!$type->canCastToGenericArrayKeys($this)) {
                return false;
            }
        } elseif ($type instanceof AssociativeArrayType) {
            return false;
        }
        return true;
    }

    public function asNonFalseyType(): Type
    {
        return NonEmptyListType::fromElementType(
            $this->element_type,
            false,
            $this->key_type
        );
    }

    public function __toString(): string
    {
        return ($this->is_nullable ? '?' : '') . 'list<' . $this->element_type->__toString() . '>';
    }

    // This is already a list.
    public function convertIntegerKeyArrayToList(): ArrayType
    {
        return $this;
    }
}
