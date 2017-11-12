<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\Element\Variable;
use Phan\Language\Scope;

class BranchScope extends Scope
{

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name) : bool
    {
        return (
            !empty($this->variable_map[$name])
            || $this->getParentScope()->hasVariableWithName($name)
        );
    }

    /**
     * @return Variable
     */
    public function getVariableByName(string $name) : Variable
    {
        return (
            $this->variable_map[$name]
            ?? $this->getParentScope()->getVariableByName($name)
        );
    }

    /**
     * @return Variable[]
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array
    {
        return $this->variable_map + $this->getParentScope()->getVariableMap();
    }
}
