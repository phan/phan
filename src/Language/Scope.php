<?php
declare(strict_types=1);
namespace Phan\Language;

use \Phan\Language\Element\Variable;

class Scope {

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
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool {
        return (
            !empty($this->variable_map[$name])
            || !empty(self::$global_variable_map[$name])
        );
    }

    /**
     * @return Variable
     */
    public function getVariableWithName(string $name) : Variable {
        return $this->variable_map[$name]
            ?? self::$global_variable_map[$name];
    }

    /**
     * @return Variable[]
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array {
        return array_merge(
            $this->variable_map,
            self::$global_variable_map
        );
    }

    /**
     * @param Variable $variable
     * A variable to add to the global scope
     *
     * @return Scope;
     */
    public function withGlobalVariable(Variable $variable) : Scope {
        self::$global_variable_map[$variable->getName()] =
            $variable;

        return $this;
    }

    /**
     * @param Variable $variable
     * A variable to add to the local scope
     *
     * @return Scope;
     */
    public function withVariable(Variable $variable) : Scope {
        $scope = clone($this);
        $scope->variable_map[$variable->getName()] = $variable;
        return $scope;
    }

}
