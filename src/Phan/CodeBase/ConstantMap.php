<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Database;
use \Phan\Exception\NotFoundException;
use \Phan\Language\Element\ClassConstant;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use \Phan\Model\Constant as ConstantModel;

trait ConstantMap
{

    /**
     * Implementing classes must support a mechanism for
     * getting a File by its path
     */
    abstract function getFileByPath(string $file_path) : File;

    /**
     * @var ClassConstant[][]
     * A map from FQSEN to name to a constant
     */
    protected $constant_map = [];

    /**
     * @return ClassConstant[][]
     * A map from FQSEN to constant
     */
    public function getConstantMap() : array
    {
        return $this->constant_map;
    }

    /**
     * @return ClassConstant[]
     * A map from name to constant
     */
    public function getConstantMapForScope(FQSEN $fqsen) : array
    {
        if (empty($this->constant_map[(string)$fqsen])) {
            return [];
        }

        return $this->constant_map[(string)$fqsen];
    }

    /**
     * @param ClassConstant[][] $constant_map
     * A map from FQSEN to ClassConstant
     *
     * @return null
     */
    public function setConstantMap(array $constant_map)
    {
        $this->constant_map = $constant_map;
    }

    /**
     * @param FQSEN $fqsen
     * The fully qualified name of the scope in which the
     * constant is defined or null if its a global constant
     *
     * @param string $name
     * The name of the constant
     *
     * @return bool
     */
    public function hasConstant(FQSEN $fqsen = null, string $name) : bool
    {
        if (!empty($this->constant_map[(string)$fqsen][$name])) {
            return true;
        }

        if (Database::isEnabled()) {
            // Otherwise, check the database
            try {
                ConstantModel::read(
                    Database::get(),
                    (string)$fqsen . '|' . $name
                );
                return true;
            } catch (NotFoundException $exception) {
                return false;
            }
        } else {
            return false;
        }

    }

    /**
     * @param FQSEN $fqsen
     * The fully qualified name of the scope in which the
     * constant is defined or null if its a global constant
     *
     * @param string $name
     * The name of the constant
     *
     * @return ClassConstant
     * Get the constant with the given FQSEN
     */
    public function getConstant(FQSEN $fqsen = null, string $name) : ClassConstant
    {

        if (empty($this->constant_map[(string)$fqsen][$name])
            && Database::isEnabled()
        ) {
            $this->constant_map[(string)$fqsen][$name] =
                ConstantModel::read(
                    Database::get(),
                    (string)$fqsen . '|' . $name
                )->getConstant();
        }

        return $this->constant_map[(string)$fqsen][$name];
    }

    /**
     * @param ClassConstant $constant
     * Any global or class-scoped constant
     *
     * @return null
     */
    public function addConstant(ClassConstant $constant)
    {
        $this->addConstantInScope(
            $constant,
            $constant->getFQSEN()
        );
    }

    /**
     * @param ClassConstant $constant
     * Any constant
     *
     * @param FullyQualifiedClassName $fqsen
     * The FQSEN to index the constant by
     *
     * @return null
     */
    public function addConstantInScope(
        ClassConstant $constant,
        FullyQualifiedClassName $fqsen
    ) {
        $name = $constant->getFQSEN()->getNameWithAlternateId();
        $this->constant_map[(string)$fqsen][$name] = $constant;
    }

    /**
     * Write each object to the database
     *
     * @return null
     */
    protected function storeConstantMap()
    {
        if (!Database::isEnabled()) {
            return;
        }

        foreach ($this->constant_map as $scope => $map) {
            foreach ($map as $name => $constant) {
                if (!$constant->getContext()->isInternal()) {
                    (new ConstantModel($constant, $scope, $name))->write(Database::get());
                }
            }
        }
    }

    /**
     * @return null
     */
    protected function flushConstantWithScopeAndName(
        string $scope,
        string $name
    ) {
        // Remove it from the database
        if (Database::isEnabled()) {
            ConstantModel::delete(
                Database::get(),
                $scope . '|' .  $name
            );
        }

        // Remove it from memory
        unset($this->constant_map[$scope][$name]);
    }
}
