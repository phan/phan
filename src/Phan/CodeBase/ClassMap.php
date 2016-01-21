<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Database;
use \Phan\Exception\NotFoundException;
use \Phan\Language\Element\Clazz;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Map;
use \Phan\Model\Clazz as ClazzModel;

trait ClassMap
{

    /**
     * Implementing classes must support a mechanism for
     * getting a File by its path
     */
    abstract function getFileByPath(string $file_path) : File;

    /**
     * @var Clazz[]|Map
     * A map from fqsen string to the class it
     * represents
     */
    protected $class_map;

    /**
     * Initialize the map
     */
    public function constructClassMap()
    {
        $this->class_map = new Map;
    }

    /**
     * Get a map from FQSEN to the class it
     * represents for all known classes.
     *
     * @return Map
     * A map from FQSEN to Clazz
     */
    public function getClassMap() : Map
    {
        return $this->class_map;
    }

    /**
     * @param Map $class_map
     * A map from FQSEN string to Clazz
     *
     * @return void
     */
    private function setClassMap(Map $class_map)
    {
        $this->class_map = $class_map;
    }

    /**
     * @return Clazz
     * A class with the given FQSEN
     *
     * @throws NotFoundException
     * An exception is thrown if the class cannot be
     * found
     */
    public function getClassByFQSEN(
        FullyQualifiedClassName $fqsen
    ) : Clazz {
        // If we can't find the class, attempt to read it from
        // the database
        if (empty($this->class_map[$fqsen])
            && Database::isEnabled()
        ) {
            $this->class_map[$fqsen] =
                ClazzModel::read(
                    Database::get(),
                    (string)$fqsen
                )->getClass();
        }

        return $this->class_map[$fqsen];
    }

    /**
     * @return bool
     * True if the exlass exists else false
     */
    public function hasClassWithFQSEN(FullyQualifiedClassName $fqsen) : bool
    {
        // Check memory for the class
        if (!empty($this->class_map[$fqsen])) {
            return true;
        }

        if (Database::isEnabled()) {
            // Otherwise, check the database
            try {
                ClazzModel::read(Database::get(), (string)$fqsen);
                return true;
            } catch (NotFoundException $exception) {
                return false;
            }
        }

        return false;
    }

    /**
     * Add a class to the code base
     *
     * @return null
     */
    public function addClass(Clazz $class)
    {
        $this->class_map[$class->getFQSEN()]
            = $class;

        // For classes that aren't internal PHP classes
        if (!$class->getContext()->isInternal()) {
            // Associate the class with the file it was found in
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
    private function addClassesByNames(array $class_name_list)
    {
        foreach ($class_name_list as $i => $class_name) {
            $clazz = Clazz::fromClassName($this, $class_name);
            $this->class_map[$clazz->getFQSEN()] = $clazz;
        }
    }

    /**
     * Write each object to the database
     *
     * @return null
     */
    protected function storeClassMap()
    {
        if (!Database::isEnabled()) {
            return;
        }

        foreach ($this->class_map as $fqsen_string => $class) {
            if (!$class->getContext()->isInternal()) {
                (new ClazzModel($class))->write(Database::get());
            }
        }
    }

    /**
     * @return null
     */
    protected function flushClassWithFQSEN(
        FullyQualifiedClassName $fqsen
    ) {
        // Remove it from the database
        if (Database::isEnabled()) {
            ClazzModel::delete(Database::get(), (string)$fqsen);
        }

        // Remove it from memory
        unset($this->class_map[$fqsen]);
    }
}
