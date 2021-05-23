<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Type;

/**
 * Phan's representation for types such as `non-empty-list` and `non-empty-list<MyClass>`
 * @see GenericArrayType for representations of `string[]` and `array<int,bool>`
 * @see ArrayShapeType for representations of `array{key:MyClass}`
 * @see ArrayType for the representation of `array`
 * @phan-pure
 */
final class NonEmptyListType extends ListType implements NonEmptyArrayInterface
{
    use NativeTypeTrait;

    /**
     * @override
     * @unused-param $key_type
     * @return NonEmptyListType
     * @phan-real-return NonEmptyListType
     * (can't change signature type until minimum supported version is php 7.4)
     */
    public static function fromElementType(
        Type $type,
        bool $is_nullable,
        int $key_type = GenericArrayType::KEY_INT
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
                new NonEmptyListType($type, $is_nullable)
            );
        }

        return $map->offsetGet($type);
    }

    protected function canCastToNonNullableType(Type $type, CodeBase $code_base): bool
    {
        if (!$type->isPossiblyTruthy()) {
            return false;
        }
        return parent::canCastToNonNullableType($type, $code_base);
    }

    /** @override */
    public function isPossiblyFalsey(): bool
    {
        return $this->is_nullable;
    }

    /** @override */
    public function isAlwaysTruthy(): bool
    {
        return !$this->is_nullable;
    }

    /** @override */
    public function asNonFalseyType(): Type
    {
        return $this->withIsNullable(false);
    }

    public function __toString(): string
    {
        return ($this->is_nullable ? '?' : '') . 'non-empty-list<' . $this->element_type->__toString() . '>';
    }

    /** @override */
    public function isDefinitelyNonEmptyArray(): bool
    {
        return true;
    }

    public function asAssociativeArrayType(bool $can_reduce_size): ArrayType
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
     * @return ListType
     * @phan-real-return ListType
     */
    public function asPossiblyEmptyArrayType(): ArrayType
    {
        return ListType::fromElementType(
            $this->element_type,
            $this->is_nullable,
            $this->key_type
        );
    }
}
