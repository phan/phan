<?php declare(strict_types=1);

namespace Phan\Language\Scope;

use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope;

/**
 * A branch scope represents a scope created by branching off of the current scope
 * (e.g. an if/elseif/else statement, a ternary conditional (`?:`) operator, etc.
 */
class BranchScope extends Scope
{
    public function __construct(Scope $scope)
    {
        parent::__construct($scope, null, $scope->flags);
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     *
     * TODO: Allow unsetting a variable within a scope, and properly account for that in this check.
     */
    public function hasVariableWithName(string $name) : bool
    {
        return (
            \array_key_exists($name, $this->variable_map)
            || $this->parent_scope->hasVariableWithName($name)
        );
    }

    public function getVariableByName(string $name) : Variable
    {
        return (
            $this->variable_map[$name]
            ?? $this->parent_scope->getVariableByName($name)
        );
    }

    public function getVariableByNameOrNull(string $name) : ?Variable
    {
        return (
            $this->variable_map[$name]
            ?? $this->parent_scope->getVariableByNameOrNull($name)
        );
    }

    /**
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array
    {
        return $this->variable_map + $this->parent_scope->getVariableMap();
    }

    /**
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in this scope
     */
    public function getVariableMapExcludingScope(?Scope $common_scope) : array
    {
        if ($this === $common_scope) {
            return [];
        }
        return $this->variable_map + $this->parent_scope->getVariableMapExcludingScope($common_scope);
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        return $this->parent_scope->getClassFQSEN();
    }

    /**
     * @return ?FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     * Return null if there is no class FQSEN.
     */
    public function getClassFQSENOrNull() : ?FullyQualifiedClassName
    {
        return $this->parent_scope->getClassFQSENOrNull();
    }

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Get the FQSEN for the closure, method or function we're in
     */
    public function getFunctionLikeFQSEN()
    {
        return $this->parent_scope->getFunctionLikeFQSEN();
    }
}
