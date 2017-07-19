<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\Element\Variable;
use Phan\Language\Scope;

class GlobalScope extends Scope {

    /**
     * @var Variable[]
     * A map from name to variables for all
     * variables registered under $GLOBALS.
     */
    private static $global_variable_map = [];

    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInClassScope() : bool
    {
        return false;
    }

    /**
     * @return bool
     * True if we're in a method/function/closure scope
     */
    public function isInFunctionLikeScope() : bool
    {
        return false;
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool
    {
        return (!empty(self::$global_variable_map[$name]));
    }

    /**
     * @return Variable
     */
    public function getVariableByName(string $name) : Variable
    {
        return self::$global_variable_map[$name];
    }

    /**
     * @return Variable[]
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array
    {
        return self::$global_variable_map;
    }

    /**
     * @param Variable $variable
     * A variable to add to the local scope
     *
     * @return Scope;
     */
    public function withVariable(Variable $variable) : Scope
    {
        $this->addVariable($variable);
        return $this;
    }

    /**
     * @return void
     */
    public function addVariable(Variable $variable)
    {
        $variable_name = $variable->getName();
        if (Variable::isHardcodedGlobalVariableWithName($variable_name)) {
            // Silently ignore globally replacing $_POST, $argv, runkit superglobals, etc.
            // with superglobals.
            // TODO: Add a warning for incompatible assignments in callers.
            return;
        }
        self::$global_variable_map[$variable->getName()] = $variable;
    }

    /**
     * @param Variable $variable
     * A variable to add to the set of global variables
     *
     * @return void
     */
    public function addGlobalVariable(Variable $variable)
    {
        $this->addVariable($variable);
    }

    /**
     * @return bool
     * True if a global variable with the given name exists
     */
    public function hasGlobalVariableWithName(string $name) : bool
    {
        return $this->hasVariableWithName($name);
    }

    /**
     * @return Variable
     * The global variable with the given name
     */
    public function getGlobalVariableByName(string $name) : Variable
    {
        return $this->getVariableByName($name);
    }

}
