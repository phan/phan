<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Variable;
use \Phan\Language\FQSEN;

trait GlobalVariableMap
{

    /**
     * @var Variable[]
     * A map from name to global variable
     */
    protected $global_variable_map = [];

    /**
     * @return Variable[]
     * A map from name to global_variable
     */
    public function getGlobalVariableMap() : array
    {
        return $this->global_variable_map;
    }

    /**
     * @param Variable[] $global_variable_map
     * A map from name to Variable
     *
     * @return null
     */
    public function setGlobalVariableMap(array $global_variable_map)
    {
        $this->global_variable_map = $global_variable_map;
    }

    /**
     * @return bool
     */
    public function hasGlobalVariableWithName(string $name) : bool
    {
        return !empty($this->global_variable_map[$name]);
    }

    /**
     * @return Variable
     * Get the global_variable with the given name
     */
    public function getGlobalVariableByName(string $name) : Variable
    {
        return $this->global_variable_map[$name];
    }

    /**
     * @param Variable $global_variable
     * Any global variable
     *
     * @return null
     */
    public function addGlobalVariable(Variable $global_variable)
    {
        $this->global_variable_map[$global_variable->getName()] =
            $global_variable;
    }
}
