<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
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
            \array_key_exists($name, $this->variable_map)
            || $this->parent_scope->hasVariableWithName($name)
        );
    }

    /**
     * @return Variable
     */
    public function getVariableByName(string $name) : Variable
    {
        return (
            $this->variable_map[$name]
            ?? $this->parent_scope->getVariableByName($name)
        );
    }

    /**
     * @return array<string,Variable>
     * A map from name to Variable in this scope
     */
    public function getVariableMap() : array
    {
        return $this->variable_map + $this->parent_scope->getVariableMap();
    }

    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInClassScope() : bool
    {
        return $this->parent_scope->isInClassScope();
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
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Get the FQSEN for the closure, method or function we're in
     */
    public function getFunctionLikeFQSEN()
    {
        return $this->parent_scope->getFunctionLikeFQSEN();
    }

    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInFunctionLikeScope() : bool
    {
        return $this->parent_scope->isInFunctionLikeScope();
    }
}
