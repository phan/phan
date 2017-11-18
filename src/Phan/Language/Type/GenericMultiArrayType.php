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
final class GenericMultiArrayType extends ArrayType
{
    /** @phan-override */
    const NAME = 'array';

    /**
     * @var Type[]
     * The list of possible types of every element in this array (2 or more)
     */
    private $element_types = [];

    /**
     * @param Type[] $types
     * The 2 or more possible types of every element in this array
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass false
     */
    protected function __construct(array $types, bool $is_nullable)
    {
        \assert(\count($types) >= 2);
        // Could de-duplicate, but callers should be able to do that as well when converting to UnionType.
        // E.g. array<int|int> is int[].
        parent::__construct('\\', self::NAME, [], false);
        $this->element_types = $types;
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

        return GenericMultiArrayType::fromElementTypes(
            $this->element_types,
            $is_nullable
        );
    }

    /**
     * @return GenericArrayType[]
     */
    public function asGenericArrayTypeInstances() : array
    {
        return \array_map(function (Type $type) {
            return GenericArrayType::fromElementType($type, $this->is_nullable);
        }, $this->element_types);
    }

    /**
     * @param Type[] $element_types
     * @param bool $is_nullable
     * @return GenericMultiArrayType
     */
    public static function fromElementTypes(
        array $element_types,
        bool $is_nullable
    ) : GenericMultiArrayType {
        return new self($element_types, $is_nullable);
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof GenericArrayType) {
            foreach ($this->genericArrayElementTypes() as $inner_type) {
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
     * @return Type[]
     * A variation of this type that is not generic.
     * i.e. 'int[]' becomes 'int'.
     */
    public function genericArrayElementTypes() : array
    {
        return $this->element_types;
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
        foreach ($this->element_types as $type) {
            $result->addUnionType(GenericArrayType::fromElementType($type, $this->is_nullable)->asExpandedTypes($code_base, $recursion_depth + 1));
        }
        return $result;
    }
}
