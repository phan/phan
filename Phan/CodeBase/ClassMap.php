<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;

trait ClassMap {

    /**
     * Implementing classes must support a mechanism for
     * getting a File by its path
     */
    abstract function getFileByPath(string $file_path) : File;

    /**
     * @var Class[]
     * A map from fqsen string to the class it
     * represents
     */
    private $class_map = [];

    /**
     * Get a map from FQSEN strings to the class it
     * represents for all known classes.
     *
     * @return Clazz[]
     * A map from FQSEN string to Clazz
     */
    public function getClassMap() : array {
        return $this->class_map;
    }

    /**
     * @param Clazz[] $class_map
     * A map from FQSEN string to Clazz
     *
     * @return null
     */
    private function setClassMap(array $class_map) {
        $this->class_map = $class_map;
    }

    /**
     * @return Clazz
     * A class with the given FQSEN
     */
    public function getClassByFQSEN(FullyQualifiedClassName $fqsen) : Clazz {
        assert(isset($this->class_map[(string)$fqsen]),
            "Class with fqsen $fqsen not found");

        return $this->class_map[(string)$fqsen];
    }

    /**
     * @return bool
     * True if the exlass exists else false
     */
    public function hasClassWithFQSEN(FullyQualifiedClassName $fqsen) : bool {
        return !empty($this->class_map[(string)$fqsen]);
    }

    /**
     * Add a class to the code base
     *
     * @return null
     */
    public function addClass(Clazz $class) {
        $this->class_map[(string)$class->getFQSEN()]
            = $class;

        if (!$class->getContext()->isInternal()) {
            $this->getFileByPath($class->getContext()->getFile())
                ->addClassFQSEN($class->getFQSEN());
        }
    }

    /**
     * @param string[] $class_name_list
     * A list of class names to load type information for
     *
     * @return null
     */
    private function addClassesByNames(array $class_name_list) {
        foreach ($class_name_list as $i => $class_name) {
            $clazz = Clazz::fromClassName($this, $class_name);
            $this->class_map[(string)$clazz->getFQSEN()] = $clazz;
        }
    }

}
