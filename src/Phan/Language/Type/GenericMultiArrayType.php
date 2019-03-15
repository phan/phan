<?php declare(strict_types=1);

namespace Phan\Language\Type;

use InvalidArgumentException;
use Phan\CodeBase;
use Phan\Debug\Frame;
use Phan\Exception\RecursionDepthException;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Language\UnionTypeBuilder;

/**
 * A temporary representation of `array<KeyType, T1|T2...>`
 *
 * Callers should split this up into multiple GenericArrayType instances.
 *
 * This is generated from phpdoc `array<int, T1|T2>` where callers expect a subclass of Type.
 */
final class GenericMultiArrayType extends ArrayType implements MultiType, GenericArrayInterface
{
    /** @phan-override */
    const NAME = 'array';

    /**
     * @var array<int,Type>
     * The list of possible types of every element in this array (2 or more)
     */
    private $element_types = [];

    /**
     * @var int
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     */
    private $key_type;

    /**
     * @param array<int,Type> $types
     * The 2 or more possible types of every element in this array
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass false
     *
     * @param int $key_type
     * Corresponds to the type of the array keys. Set this to a GenericArrayType::KEY_* constant.
     *
     * @throws InvalidArgumentException if there are less than 2 types in $types
     */
    protected function __construct(array $types, bool $is_nullable, int $key_type)
    {
        if (\count($types) < 2) {
            throw new InvalidArgumentException('Expected $types to have at least 2 array elements');
        }
        // Could de-duplicate, but callers should be able to do that as well when converting to UnionType.
        // E.g. array<int|int> is int[].
        parent::__construct('\\', self::NAME, [], false);
        $this->element_types = $types;
        $this->is_nullable = $is_nullable;
        $this->key_type = $key_type;
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

        return GenericMultiArrayType::fromElementTypes(
            $this->element_types,
            $is_nullable,
            $this->key_type
        );
    }

    /**
     * @return array<int,GenericArrayType>
     * @override
     */
    public function asIndividualTypeInstances() : array
    {
        return \array_map(function (Type $type) : GenericArrayType {
            return GenericArrayType::fromElementType($type, $this->is_nullable, $this->key_type);
        }, UnionType::normalizeMultiTypes($this->element_types));
    }

    /**
     * Public creator of GenericMultiArrayType instances
     *
     * @param array<int,Type> $element_types
     * @param bool $is_nullable
     * @param int $key_type
     * @return GenericMultiArrayType
     */
    public static function fromElementTypes(
        array $element_types,
        bool $is_nullable,
        int $key_type
    ) : GenericMultiArrayType {
        return new self($element_types, $is_nullable, $key_type);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof GenericArrayType) {
            return $this->genericArrayElementUnionType()->canCastToUnionType(
                $type->genericArrayElementUnionType()
            );
        }

        // TODO: More precise about checking if can cast to ArrayShapeType

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
     * @var ?UnionType the normalized element union type. Computed from `$this->element_types`.
     */
    private $element_types_union_type;

    /**
     * @return UnionType
     * A variation of this type that is not generic.
     * i.e. '(int|string)[]' becomes 'int|string'.
     */
    public function genericArrayElementUnionType() : UnionType
    {
        return $this->element_types_union_type
            ?? ($this->element_types_union_type = UnionType::of(
                UnionType::normalizeMultiTypes($this->element_types)
            ));
    }

    public function __toString() : string
    {
        $string = 'array<' . \implode('|', $this->element_types) . '>';
        if ($this->is_nullable) {
            $string = '?' . $string;
        }
        return $string;
    }

    /**
     * @param CodeBase $code_base
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
     * @override
     */
    public function asExpandedTypes(
        CodeBase $code_base,
        int $recursion_depth = 0
    ) : UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        if ($recursion_depth >= 20) {
            throw new RecursionDepthException("Recursion has gotten out of hand: " . Frame::getExpandedTypesDetails());
        }

        // TODO: Use UnionType::merge from a future change?
        $result = new UnionTypeBuilder();
        try {
            foreach ($this->element_types as $type) {
                $result->addUnionType(
                    GenericArrayType::fromElementType(
                        $type,
                        $this->is_nullable,
                        $this->key_type
                    )->asExpandedTypes($code_base, $recursion_depth + 1)
                );
            }
        } catch (RecursionDepthException $_) {
            return ArrayType::instance($this->is_nullable)->asUnionType();
        }
        return $result->getUnionType();
    }

    /**
     * @param CodeBase $code_base
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
     * @override
     */
    public function asExpandedTypesPreservingTemplate(
        CodeBase $code_base,
        int $recursion_depth = 0
    ) : UnionType {
        // We're going to assume that if the type hierarchy
        // is taller than some value we probably messed up
        // and should bail out.
        if ($recursion_depth >= 20) {
            throw new RecursionDepthException("Recursion has gotten out of hand: " . Frame::getExpandedTypesDetails());
        }

        // TODO: Use UnionType::merge from a future change?
        $result = new UnionTypeBuilder();
        try {
            foreach ($this->element_types as $type) {
                $result->addUnionType(
                    GenericArrayType::fromElementType(
                        $type,
                        $this->is_nullable,
                        $this->key_type
                    )->asExpandedTypesPreservingTemplate($code_base, $recursion_depth + 1)
                );
            }
        } catch (RecursionDepthException $_) {
            return ArrayType::instance($this->is_nullable)->asUnionType();
        }
        return $result->getUnionType();
    }
}
