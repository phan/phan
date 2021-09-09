<?php

declare(strict_types=1);

namespace Phan\Language;

/**
 * This is used to represent a union type that has various annotations.
 * Currently, the only annotation is is_possibly_undefined, which is used in optional fields of array shapes.
 * (It might eventually be used for variables that are defined in some branches but not others)
 *
 * NOTE: Instances should be immutable after UnionType returns them.
 * They are not unique for combination of types and options.
 *
 * The internal representation may change in the future.
 * @phan-pure
 */
class AnnotatedUnionType extends UnionType
{
    protected const DEFINITELY_UNDEFINED = 1;

    /**
     * @var bool|1 is this union type possibly undefined
     * (e.g. a possibly undefined array shape offset)
     */
    protected $is_possibly_undefined = false;

    /**
     * @override
     * @suppress PhanAccessReadOnlyProperty this is the only way to set is_possibly_undefined
     */
    public function withIsPossiblyUndefined(bool $is_possibly_undefined): UnionType
    {
        if ($this->is_possibly_undefined === $is_possibly_undefined) {
            return $this;
        }
        if (!$is_possibly_undefined) {
            return UnionType::of($this->getTypeSet(), $this->getRealTypeSet());
        }
        $result = clone($this);
        $result->is_possibly_undefined = $is_possibly_undefined;
        return $result;
    }

    /**
     * @param bool|1 $is_possibly_undefined
     * @suppress PhanAccessReadOnlyProperty this is the only way to set is_possibly_undefined
     */
    private function withIsPossiblyUndefinedRaw($is_possibly_undefined): UnionType
    {
        if ($this->is_possibly_undefined === $is_possibly_undefined) {
            return $this;
        }
        if (!$is_possibly_undefined) {
            return UnionType::of($this->getTypeSet(), $this->getRealTypeSet());
        }
        $result = clone($this);
        $result->is_possibly_undefined = $is_possibly_undefined;
        return $result;
    }
    /**
     * @override
     * @suppress PhanAccessReadOnlyProperty this is the only way to set is_possibly_undefined
     */
    public function withIsDefinitelyUndefined(): UnionType
    {
        if ($this->is_possibly_undefined === self::DEFINITELY_UNDEFINED) {
            return $this;
        }
        $result = clone($this);
        $result->is_possibly_undefined = self::DEFINITELY_UNDEFINED;
        return $result;
    }


    public function asSingleScalarValueOrNull()
    {
        if ($this->is_possibly_undefined) {
            return null;
        }
        return parent::asSingleScalarValueOrNull();
    }

    public function asSingleScalarValueOrNullOrSelf()
    {
        if ($this->is_possibly_undefined) {
            return $this;
        }
        return parent::asSingleScalarValueOrNullOrSelf();
    }

    public function asValueOrNullOrSelf()
    {
        if ($this->is_possibly_undefined) {
            return $this;
        }
        return parent::asValueOrNullOrSelf();
    }

    public function isPossiblyUndefined(): bool
    {
        return (bool) $this->is_possibly_undefined;
    }

    public function isDefinitelyUndefined(): bool
    {
        return $this->is_possibly_undefined === self::DEFINITELY_UNDEFINED;
    }

    public function isNull(): bool
    {
        return $this->is_possibly_undefined === self::DEFINITELY_UNDEFINED || parent::isNull();
    }

    public function isRealTypeNullOrUndefined(): bool
    {
        return $this->is_possibly_undefined === self::DEFINITELY_UNDEFINED || parent::isRealTypeNullOrUndefined();
    }

    public function __toString(): string
    {
        $result = parent::__toString();
        if ($this->is_possibly_undefined) {
            return ($result !== '' ? $result : 'mixed') . '=';
        }
        return $result;
    }

    /**
     * Add a type name to the list of types
     * @override
     */
    public function withType(Type $type): UnionType
    {
        return parent::withType($type)->withIsPossiblyUndefined(false);
    }

    /**
     * Remove a type name from the list of types
     * @override
     */
    public function withoutType(Type $type): UnionType
    {
        return parent::withoutType($type)->withIsPossiblyUndefined(false);
    }

    /**
     * Returns a union type which adds the given types to this type
     * @override
     */
    public function withUnionType(UnionType $union_type): UnionType
    {
        return parent::withUnionType($union_type)->withIsPossiblyUndefined(false);
    }

    /**
     * @return bool - True if not empty, not possibly undefined, and at least one type is NullType or nullable.
     * XXX consider merging into containsNullable
     * @override
     */
    public function containsNullableOrUndefined(): bool
    {
        return $this->is_possibly_undefined || $this->containsNullable();
    }

    /**
     * @override
     */
    public function containsFalsey(): bool
    {
        return $this->is_possibly_undefined || parent::containsFalsey();
    }

    /**
     * @override
     */
    public function generateUniqueId(): string
    {
        $id = parent::generateUniqueId();
        if ($this->is_possibly_undefined) {
            return '(' . $id . ')=';
        }
        return $id;
    }

    /**
     * Returns a union type with an empty real type set (including in elements of generic arrays, etc.)
     */
    public function eraseRealTypeSetRecursively(): UnionType
    {
        $type_set = $this->getTypeSet();
        $new_type_set = [];
        foreach ($type_set as $type) {
            $new_type_set[] = $type->withErasedUnionTypes();
        }
        $real_type_set = $this->getRealTypeSet();
        if (!$real_type_set && $new_type_set === $type_set) {
            return $this;
        }
        $result = new AnnotatedUnionType($new_type_set, false, $real_type_set);
        // @phan-suppress-next-line PhanAccessReadOnlyProperty
        $result->is_possibly_undefined = $this->is_possibly_undefined;
        return $result;
    }

    public function convertUndefinedToNullable(): UnionType
    {
        if ($this->is_possibly_undefined) {
            return $this->nullableClone()->withIsPossiblyUndefined(false);
        }
        return $this;
    }

    /**
     * @override
     */
    public function isEqualTo(UnionType $union_type): bool
    {
        if ($this === $union_type) {
            return true;
        }
        if ($union_type instanceof AnnotatedUnionType) {
            if ($this->is_possibly_undefined !== $union_type->is_possibly_undefined) {
                return false;
            }
        } elseif ($this->is_possibly_undefined) {
            return false;
        }
        $type_set = $this->getTypeSet();
        $other_type_set = $union_type->getTypeSet();
        if (\count($type_set) !== \count($other_type_set)) {
            return false;
        }
        foreach ($type_set as $type) {
            if (!\in_array($type, $other_type_set, true)) {
                return false;
            }
        }
        return true;
    }

    public function isIdenticalTo(UnionType $union_type): bool
    {
        if ($this === $union_type) {
            return true;
        }
        if (!$this->isEqualTo($union_type)) {
            return false;
        }
        $real_type_set = $this->getRealTypeSet();
        $other_real_type_set = $union_type->getRealTypeSet();
        if (\count($real_type_set) !== \count($other_real_type_set)) {
            return false;
        }
        foreach ($real_type_set as $type) {
            if (!\in_array($type, $other_real_type_set, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Converts the real part of the union type to a standalone union type
     * @override
     */
    public function getRealUnionType(): UnionType
    {
        $real_type_set = $this->getRealTypeSet();
        if ($this->getTypeSet() === $real_type_set) {
            return $this;
        }
        return (new AnnotatedUnionType($real_type_set, true, $real_type_set))->withIsPossiblyUndefinedRaw($this->is_possibly_undefined);
    }

    /**
     * Converts a phpdoc type into the real union type equivalent.
     * @override
     */
    public function asRealUnionType(): UnionType
    {
        $type_set = $this->getTypeSet();
        return (new AnnotatedUnionType($type_set, true, $type_set))->withIsPossiblyUndefinedRaw($this->is_possibly_undefined);
    }
}
