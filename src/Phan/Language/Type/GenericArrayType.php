<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

final class GenericArrayType extends ArrayType
{
    /** @phan-override */
    const NAME = 'array';

    /**
     * @var Type|null
     * The type of every element in this array
     */
    private $element_type = null;

    /**
     * @param Type $type
     * The type of every element in this array
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     */
    protected function __construct(Type $type, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], false);
        $this->element_type = $type;
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

        return GenericArrayType::fromElementType(
            $this->element_type,
            $is_nullable
        );
    }


    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type instanceof GenericArrayType) {
            return $this->genericArrayElementType()
                ->canCastToType($type->genericArrayElementType());
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

    /**
     * @param Type $type
     * The element type for an array.
     *
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return GenericArrayType
     * Get a type representing an array of the given type
     */
    public static function fromElementType(
        Type $type,
        bool $is_nullable
    ) : GenericArrayType
    {
        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_map_non_nullable = null;
        static $canonical_object_map_nullable = null;

        if (!$canonical_object_map_non_nullable) {
            $canonical_object_map_non_nullable = new \SplObjectStorage();
        }

        if (!$canonical_object_map_nullable) {
            $canonical_object_map_nullable = new \SplObjectStorage();
        }

        $map = $is_nullable
            ? $canonical_object_map_nullable
            : $canonical_object_map_non_nullable;

        if (!$map->contains($type)) {
            $map->attach(
                $type,
                new GenericArrayType($type, $is_nullable)
            );
        }

        return $map->offsetGet($type);
    }

    public function isGenericArray() : bool
    {
        return true;
    }

    /**
     * @return Type
     * A variation of this type that is not generic.
     * i.e. 'int[]' becomes 'int'.
     */
    public function genericArrayElementType() : Type
    {
        return $this->element_type;
    }

    public function __toString() : string
    {
        $string = "{$this->element_type}[]";

        if ($this->getIsNullable()) {
            $string = '?' . $string;
        }

        return $string;
    }
}
