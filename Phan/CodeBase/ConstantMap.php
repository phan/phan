<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Database;
use \Phan\Language\Element\Constant;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Model\Constant as ConstantModel;

trait ConstantMap {

    /**
     * @var Constant[][]
     * A map from FQSEN to name to a constant
     */
    protected $constant_map = [];

    /**
     * @return Constant[][]
     * A map from FQSEN to constant
     */
    public function getConstantMap() : array {
        return $this->constant_map;
    }

    /**
     * @return Constant[]
     * A map from name to constant
     */
    public function getConstantMapForScope(FQSEN $fqsen) : array {
        if (empty($this->constant_map[(string)$fqsen])) {
            return [];
        }

        return $this->constant_map[(string)$fqsen];
    }

    /**
     * @param Constant[][] $constant_map
     * A map from FQSEN to Constant
     *
     * @return null
     */
    public function setConstantMap(array $constant_map) {
        $this->constant_map = $constant_map;
    }

    /**
     * @return bool
     */
    public function hasConstant(FQSEN $fqsen, string $name) : bool {
        return !empty($this->constant_map[(string)$fqsen][$name]);
    }

    /**
     * @return Constant
     * Get the constant with the given FQSEN
     */
    public function getConstant(FQSEN $fqsen, string $name) : Constant {
        return $this->constant_map[(string)$fqsen][$name];
    }

    /**
     * @param Constant $constant
     * Any global or class-scoped constant
     *
     * @return null
     */
    public function addConstant(Constant $constant) {
        $this->addConstantInScope(
            $constant,
            $constant->getFQSEN()
        );
    }

    /**
     * @param Constant $constant
     * Any constant
     *
     * @param FullyQualifiedClassName $fqsen
     * The FQSEN to index the constant by
     *
     * @return null
     */
    public function addConstantInScope(
        Constant $constant,
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
    protected function storeConstantMap() {
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
            ConstantModel::delete(Database::get(),
                $scope . '|' .  $name
            );
        }

        // Remove it from memory
        unset($this->constant_map[$scope][$name]);
    }

}
