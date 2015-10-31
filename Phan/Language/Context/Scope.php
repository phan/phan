<?php
declare(strict_types=1);
namespace Phan\Language\Context;

use \Phan\Language\Element\Variable;

class Scope {
    /**
     * @var Variable[]
     */
    private $variable_map = [];

    public function __construct() {
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool {
        return !empty($this->variable_map[$name]);
    }

    /**
     * @return Variable
     */
    public function getVariableWithName(string $name) : Variable {
        return $this->variable_map[$name];
    }

    /**
     * @return Scope;
     */
    public function withVarible(Variable $variable) : Scope {
        $scope = clone($this);
        $scope->variable_map[$variable->getName()] = $variable;
        return $scope;
    }

}
