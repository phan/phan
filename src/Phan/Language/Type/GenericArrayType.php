<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class GenericArrayType extends ArrayType
{
    const NAME = 'array';

    /**
     * @var Type|null
     * The type of every element in this array
     */
    private $element_type = null;

    /**
     * @param Type $type
     * The type of every element in this array
     */
    protected function __construct(Type $type, bool $is_nullable)
    {
        parent::__construct('\\', self::NAME, [], $is_nullable);
        $this->element_type = $type;
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

        $d = strtolower((string)$type);
        if ($d[0] == '\\') {
            $d = substr($d, 1);
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
     * @return GenericArrayType
     * Get a type representing an array of the given type
     */
    public static function fromElementType(Type $type) : GenericArrayType
    {

        // Make sure we only ever create exactly one
        // object for any unique type
        static $canonical_object_map = null;

        if (!$canonical_object_map) {
            $canonical_object_map = new \SplObjectStorage();
        }

        if (!$canonical_object_map->contains($type)) {
            $canonical_object_map->attach(
                $type,
                new GenericArrayType($type, false)
            );
        }

        return $canonical_object_map->offsetGet($type);
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
        if ($this->is_nullable === $is_nullable) {
            return $this;
        }
        // FIXME: fully implement follow up for https://github.com/etsy/phan/issues/665
        // Resolve the ambiguity of (?string)[] vs ?(string[]) in __toString()
        return new static($this->element_type, $is_nullable);
    }
}
