<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\AnnotatedUnionType;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents a stdClass with a set of known property shape constraints.
 *
 * This behaves similarly to {@see ArrayShapeType} but for dynamic stdClass instances.
 * The shape information is opportunistic: operations that make the shape unreliable
 * should fall back to plain stdClass.
 */
final class StdClassShapeType extends Type
{
    public const NAME = 'stdClass';

    /**
     * @var array<string,UnionType|AnnotatedUnionType>
     * Maps property names to their inferred union types.
     */
    private $field_types = [];

    /**
     * Lazily populated union type consisting of `\stdClass` (used when erasing shape information).
     * @var ?UnionType
     */
    private $stdclass_union_type = null;

    /**
     * @param array<string,UnionType|AnnotatedUnionType> $field_types
     * @param bool $is_nullable
     */
    protected function __construct(array $field_types, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->field_types = $field_types;
    }

    /**
     * Create a shaped stdClass type from known property types.
     *
     * @param array<string,UnionType|AnnotatedUnionType> $field_types map of property name to inferred type
     * @param bool $is_nullable whether the shaped type is nullable
     * @throws \InvalidArgumentException|\Phan\Exception\FQSENException if the fallback \stdClass type cannot be constructed
     */
    public static function fromFieldTypes(array $field_types, bool $is_nullable): Type
    {
        if (!$field_types) {
            // Without any shape information, fall back to plain stdClass
            return Type::fromFullyQualifiedString('\\stdClass')->withIsNullable($is_nullable);
        }
        return new self($field_types, $is_nullable);
    }

    /**
     * Returns an immutable version of this type with the given nullability.
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($this->is_nullable === $is_nullable) {
            return $this;
        }
        return new self($this->field_types, $is_nullable);
    }

    /**
     * Returns the union type corresponding to a plain stdClass (ignores shape fields).
     *
     * @throws \InvalidArgumentException|\Phan\Exception\FQSENException if the fallback \stdClass type cannot be constructed
     */
    public function asPlainStdClassUnionType(): UnionType
    {
        return $this->stdclass_union_type
            ?? ($this->stdclass_union_type = UnionType::of([
                Type::fromFullyQualifiedString('\\stdClass')->withIsNullable($this->is_nullable),
            ]));
    }

    /**
     * Returns the map of property names to their associated union types.
     */
    /**
     * Returns the stored mapping of property names to their inferred union types.
     *
     * @return array<string,UnionType|AnnotatedUnionType>
     */
    public function getFieldTypes(): array
    {
        return $this->field_types;
    }

    /**
     * Checks if the shape contains the given property.
     */
    public function hasFieldWithName(string $field_name): bool
    {
        return \array_key_exists($field_name, $this->field_types);
    }

    /**
     * Returns the union type recorded for the property, if any.
     */
    public function getFieldType(string $field_name): ?UnionType
    {
        return $this->field_types[$field_name] ?? null;
    }

    /**
     * Returns a shape type with $field_name updated to $field_type.
     * @param bool $is_optional whether the field is possibly undefined.
     */
    public function withField(string $field_name, UnionType $field_type, bool $is_optional): StdClassShapeType
    {
        $new_field_types = $this->field_types;
        $new_field_types[$field_name] = self::applyOptionalFlag($field_type, $is_optional);
        return new self($new_field_types, $this->is_nullable);
    }

    /**
     * Returns a shape type without the named field. Falls back to plain stdClass when no fields remain.
     *
     * @throws \InvalidArgumentException|\Phan\Exception\FQSENException if the fallback \stdClass type cannot be constructed
     */
    public function withoutField(string $field_name): Type
    {
        if (!\array_key_exists($field_name, $this->field_types)) {
            return $this;
        }
        $new_field_types = $this->field_types;
        unset($new_field_types[$field_name]);
        return self::fromFieldTypes($new_field_types, $this->is_nullable);
    }

    /**
     * Returns a shape with the union of the current field type and the provided one.
     */
    public function withMergedField(string $field_name, UnionType $field_type, bool $is_optional): StdClassShapeType
    {
        $existing_union_type = $this->field_types[$field_name] ?? null;
        if ($existing_union_type instanceof UnionType) {
            $normalized_existing = $is_optional ? $existing_union_type : $existing_union_type->withIsPossiblyUndefined(false);
            $normalized_new = $field_type->withIsPossiblyUndefined(false);
            $field_type = $normalized_existing->withUnionType($normalized_new);
        }
        $field_type = self::applyOptionalFlag($field_type, $is_optional);
        if (!$is_optional && $field_type->isPossiblyUndefined()) {
            $field_type = UnionType::of($field_type->getTypeSet(), $field_type->getRealTypeSet());
        }
        $new_field_types = $this->field_types;
        $new_field_types[$field_name] = $field_type;
        return new self($new_field_types, $this->is_nullable);
    }

    /**
     * Merge this shape with another shape. Shared fields get unioned; fields missing on either side become optional.
     */
    public function mergeWithShape(StdClassShapeType $other): StdClassShapeType
    {
        $builder = $this->field_types;
        foreach ($other->field_types as $field_name => $field_union_type) {
            if (isset($builder[$field_name])) {
                $existing_union_type = $builder[$field_name];
                $combined = $existing_union_type->withIsPossiblyUndefined(false)
                    ->withUnionType($field_union_type->withIsPossiblyUndefined(false));
                if ($existing_union_type->isPossiblyUndefined() || $field_union_type->isPossiblyUndefined()) {
                    $combined = $combined->withIsPossiblyUndefined(true);
                }
                $builder[$field_name] = $combined;
            } else {
                $builder[$field_name] = $field_union_type->withIsPossiblyUndefined(true);
            }
        }
        foreach ($builder as $field_name => $field_union_type) {
            if (!isset($other->field_types[$field_name])) {
                $builder[$field_name] = $field_union_type->withIsPossiblyUndefined(true);
            }
        }
        return new self($builder, $this->is_nullable || $other->is_nullable);
    }

    /**
     * Returns all unique union types used in the shape's properties.
     *
     * @return list<UnionType>
     */
    public function getUniqueValueUnionTypes(): array
    {
        $result = [];
        $seen = [];
        foreach ($this->field_types as $field_type) {
            $key = $field_type->__toString();
            if (isset($seen[$key])) {
                continue;
            }
            $result[] = $field_type;
            $seen[$key] = true;
        }
        return $result;
    }

    /**
     * Returns the strongest type guaranteed for property $field_name after an isset/array_key_exists check.
     */
    public function getFieldTypeAfterIsset(string $field_name): ?UnionType
    {
        $field_union_type = $this->getFieldType($field_name);
        if (!$field_union_type) {
            return null;
        }
        if ($field_union_type->isPossiblyUndefined()) {
            return $field_union_type->withIsPossiblyUndefined(false);
        }
        return $field_union_type;
    }

    /**
     * Marks the provided union type as possibly undefined when needed.
     */
    private static function applyOptionalFlag(UnionType $type, bool $is_optional): UnionType
    {
        return $type->withIsPossiblyUndefined($is_optional);
    }

    public function __toString(): string
    {
        if (!$this->field_types) {
            return ($this->is_nullable ? '?' : '') . '\\' . self::NAME;
        }
        $parts = [];
        foreach ($this->field_types as $name => $union_type) {
            $key = \is_string($name) ? ArrayShapeType::escapeKey($name) : (string)$name;
            $value_repr = $union_type->__toString();
            if (\str_ends_with($value_repr, '=')) {
                $parts[] = $key . '?:' . \substr($value_repr, 0, -1);
            } else {
                $parts[] = $key . ':' . $value_repr;
            }
        }
        \sort($parts);
        return ($this->is_nullable ? '?' : '') . '\\' . self::NAME . '{' . \implode(',', $parts) . '}';
    }

    public function toErrorMessageString(): string
    {
        return $this->__toString();
    }
}
