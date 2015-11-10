<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\Type;

class GenericArrayType extends ArrayType {
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
    public function __construct(Type $type) {
        parent::__construct(self::NAME, '\\');
        $this->element_type = $type;
    }

    public function isGeneric() : bool {
        return true;
    }

    /**
     * @return Type
     * A variation of this type that is not generic.
     * i.e. 'int[]' becomes 'int'.
     */
    public function asNonGenericType() : Type {
        return $this->element_type;
    }


    public function __toString() : string {
        return "{$this->element_type}[]";
    }
}
