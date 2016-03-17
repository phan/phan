<?php
declare(strict_types=1);
namespace Phan\Language;

use Phan\Language\Element\Variable;

class Scope
{

    /**
     * @var Variable[]
     * A map from name to variables for all
     * variables registered under $GLOBALS.
     */
    static $global_variable_map = [];

    /**
     * @var Variable[]
     */
    private $variable_map = [];

    /**
     * When entering a new file, we need to copy all globally
     * scope variables to the local scope so that they become
     * accessible.
     *
     * @return void
     */
    public function copyGlobalToLocal()
    {
        foreach (self::$global_variable_map as $name => $variable) {
            $this->addVariable($variable);
        }
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool
    {
        return (
            !empty($this->variable_map[$name])
            || !empty(self::$global_variable_map[$name])
        );
    }

    /**
     * @return Variable
     */
    public function getVariableWithName(string $name) : Variable
    {
        return $this->variable_map[$name]
            ?? self::$global_variable_map[$name];
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this local scope (ignoring the global scope)
     */
    public function hasLocalVariableWithName(string $name) : bool
    {
        return (
            !empty($this->variable_map[$name])
        );
    }

    /**
     * @return Variable
     */
    public function getLocalVariableWithName(string $name) : Variable
    {
        return $this->variable_map[$name];
    }

    /**
     * @return Variable[]
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array
    {
        return $this->variable_map;
    }

    /**
     * @param Variable $variable
     * A variable to add to the global scope
     *
     * @return Scope
     */
    public function withGlobalVariable(Variable $variable) : Scope
    {
        // Add the variable globally
        self::$global_variable_map[$variable->getName()] =
            $variable;

        // Add it locally as well
        return $this->withVariable($variable);
    }

    /**
     * @param Variable $variable
     * A variable to add to the local scope
     *
     * @return Scope;
     */
    public function withVariable(Variable $variable) : Scope
    {
        $scope = clone($this);
        $scope->addVariable($variable);
        return $scope;
    }

    /**
     * @return void
     */
    public function addVariable(Variable $variable)
    {
        $this->variable_map[$variable->getName()] = $variable;
    }

    /**
     * @return string
     * A string representation of this scope
     */
    public function __toString() : string
    {
        return implode(',', $this->getVariableMap());
    }
}
