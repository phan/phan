<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Type;

/**
 * Phan's representation for types such as `associative-array<MyClass>` and `associative-array<int, MyClass>`
 * @see GenericArrayType for representations of `string[]` and `array<int,bool>`
 * @see ArrayShapeType for representations of `array{key:MyClass}`
 * @see ArrayType for the representation of `array`
 * @phan-pure
 */
class AssociativeArrayType extends GenericArrayType
{
    protected function __construct(Type $type, bool $is_nullable, int $key_type)
    {
        parent::__construct($type, $is_nullable, $key_type);
    }

    public static function fromElementType(
        Type $type,
        bool $is_nullable,
        int $key_type = GenericArrayType::KEY_MIXED
    ): GenericArrayType {
        if ($key_type === GenericArrayType::KEY_STRING) {
            return GenericArrayType::fromElementType($type, $is_nullable, GenericArrayType::KEY_STRING);
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
                new AssociativeArrayType($type, $is_nullable, $key_type)
            );
        }

        return $map->offsetGet($type);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        return $this->canCastToTypeCommon($type) &&
            parent::canCastToNonNullableType($type, $code_base);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type, CodeBase $code_base): bool
    {
        return $this->canCastToTypeCommon($type) &&
            parent::canCastToNonNullableTypeWithoutConfig($type, $code_base);
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
        } elseif ($type instanceof ListType) {
            return false;
        }
        return true;
    }

    public function asNonFalseyType(): Type
    {
        return NonEmptyAssociativeArrayType::fromElementType(
            $this->element_type,
            false,
            $this->key_type
        );
    }

    public function __toString(): string
    {
        return ($this->is_nullable ? '?' : '') .
            'associative-array<' . self::KEY_NAMES[$this->key_type] . ',' . $this->element_type->__toString() . '>';
    }

    /**
     * @unused-param $can_reduce_size
     */
    public function asAssociativeArrayType(bool $can_reduce_size): ArrayType
    {
        return $this;
    }
}
