<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\CodeBase;

/**
 * Callers should split this up into multiple GenericArrayType instances
 *
 * This is generated from phpdoc array<int, T1|T2> where callers expect a subclass of Type.
 */
final class ArrayShapeType extends ArrayType
{
    /** @phan-override */
    const NAME = 'array';

    /**
     * @var array<string|int,Type>
     * Maps 0 or more field names to the corresponding types
     */
    private $field_types = [];

    /**
     * @param array<string|int,Type> $types
     * Maps 0 or more field names to the corresponding types
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass false
     */
    protected function __construct(array $types, bool $is_nullable)
    {
        // Could de-duplicate, but callers should be able to do that as well when converting to UnionType.
        // E.g. array<int|int> is int[].
        parent::__construct('\\', self::NAME, [], false);
        $this->field_types = $types;
        $this->is_nullable = $is_nullable;
    }

    /**
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     */
    public function withIsNullable(bool $is_nullable) : Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }

        return ArrayShapeType::fromFieldTypes(
            $this->field_types,
            $is_nullable
        );
    }

    /**
     * @return ArrayType[]
     */
    public function asGenericArrayTypeInstances() : array
    {
        if (\count($this->field_types) === 0) {
            // There are 0 fields, so we know nothing about the field types (And there's no way to indicate an empty array yet)
            return [ArrayType::instance($this->is_nullable)];
        }

        $key_type = GenericArrayType::getKeyTypeForArrayLiteral($this->field_types);

        return \array_map(function (Type $type) use ($key_type) {
            return GenericArrayType::fromElementType($type, $this->is_nullable, $key_type);
        }, UnionType::normalizeGenericMultiArrayTypes($this->field_types));
    }

    /**
     * @param array<string|int,Type> $field_types
     * @param bool $is_nullable
     * @return ArrayShapeType
     */
    public static function fromFieldTypes(
        array $field_types,
        bool $is_nullable
    ) : ArrayShapeType {
        return new self($field_types, $is_nullable);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof GenericArrayType) {
            foreach ($this->arrayShapeFieldTypes() as $inner_type) {
                if ($type->canCastToType($type->genericArrayElementType())) {
                    return true;
                }
            }
            return false;
        }

        if ($type->isArrayLike()) {
            return true;
        }

        $d = \strtolower((string)$type);
        if ($d[0] == '\\') {
            $d = \substr($d, 1);
        }
        if ($d === 'callable') {
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    public function isGenericArray() : bool
    {
        return true;
    }

    /**
     * @return array<string|int,Type>
     * An array of mapping field keys of this type to field types
     */
    public function arrayShapeFieldTypes() : array
    {
        return $this->field_types;
    }

    public function __toString() : string
    {
        $parts = [];
        foreach ($this->field_types as $key => $value) {
            $parts[] = "$key:$value";
        }
        $string = 'array{' . \implode(',', $parts) . '}';
        if ($this->is_nullable) {
            $string = '?' . $string;
        }
        return $string;
    }

    /**
     * @param CodeBase
     * The code base to use in order to find super classes, etc.
     *
     * @param $recursion_depth
     * This thing has a tendency to run-away on me. This tracks
     * how bad I messed up by seeing how far the expanded types
     * go
     *
     * @return UnionType
     * Expands class types to all inherited classes returning
     * a superset of this type.
     *
     * TODO: Once Phan has full support for ArrayShapeType in the type system,
     * make asExpandedTypes return a UnionType with a single ArrayShapeType?
     * @override
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ) : UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        \assert(
            $recursion_depth < 20,
            "Recursion has gotten out of hand"
        );
        // TODO: Use UnionType::merge from a future change?
        $result = new UnionType();
        $key_type = GenericArrayType::getKeyTypeForArrayLiteral($this->field_types);
        foreach ($this->field_types as $type) {
            $result->addUnionType(GenericArrayType::fromElementType($type, $this->is_nullable, $key_type)->asExpandedTypes($code_base, $recursion_depth + 1));
        }
        return $result;
    }
}
