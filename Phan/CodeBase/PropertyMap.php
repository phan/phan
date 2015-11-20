<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;

/**
 * Information pertaining to PHP code files that we've read
 */
trait PropertyMap {

    /**
     * @var Property[]
     * A map from FQSEN to a property
     */
    protected $property_map = [];

    /**
     * @return Property[]
     * A map from FQSEN to property
     */
    public function getPropertyMap() : array {
        return $this->property_map;
    }

    /**
     * @param Property[] $property_map
     * A map from FQSEN to Property
     *
     * @return null
     */
    public function setPropertyMap(array $property_map) {
        $this->property_map = $property_map;
    }

    /**
     * @return bool
     */
    public function hasPropertyWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->property_map[(string)$fqsen]);
    }

    /**
     * @return Property
     * Get the property with the given FQSEN
     */
    public function getPropertyByFQSEN(FQSEN $fqsen) : Property {
        return $this->property_map[(string)$fqsen];
    }

    /**
     * @param Property $property
     * Any global or class-scoped property
     *
     * @return null
     */
    public function addProperty(Property $property) {
        $this->property_map[(string)$property->getFQSEN()] = $property;
    }
}

