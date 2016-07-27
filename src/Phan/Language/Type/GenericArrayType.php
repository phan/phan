<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

class GenericArrayType extends ArrayType
{
    const NAME = 'array';

    /**
     * @var Type
     * The type of every element in this array
     */
    private $element_type = null;

    /**
     * @param Type $type
     * The type of every element in this array
     */
    protected function __construct(Type $type)
    {
        parent::__construct('\\', self::NAME, []);
        $this->element_type = $type;
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
                new GenericArrayType($type)
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
        return "{$this->element_type}[]";
    }
}
