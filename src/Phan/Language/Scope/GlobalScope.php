<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use AssertionError;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope;

/**
 * Represents the global scope (and stores global variables)
 */
final class GlobalScope extends Scope
{
    /**
     * Deliberate no-op
     */
    public function __construct()
    {
    }

    /**
     * @var array<string,Variable>
     * A map from name to variables for all
     * variables registered under $GLOBALS.
     */
    private static $global_variable_map = [];

    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInClassScope(): bool
    {
        return false;
    }

    /**
     * @return bool
     * True if we're in a method/function/closure scope
     */
    public function isInFunctionLikeScope(): bool
    {
        return false;
    }

    public function isInElementScope(): bool
    {
        return false;
    }

    public function isInMethodLikeScope(): bool
    {
        return false;
    }

    public function hasAnyTemplateType(): bool
    {
        return false;
    }

    public function getTemplateTypeMap(): array
    {
        return [];
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name): bool
    {
        return \array_key_exists($name, self::$global_variable_map);
    }

    public function getVariableByName(string $name): Variable
    {
        return self::$global_variable_map[$name];
    }

    public function getVariableByNameOrNull(string $name): ?Variable
    {
        return self::$global_variable_map[$name] ?? null;
    }

    /**
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in the global scope.
     */
    public function getVariableMap(): array
    {
        return self::$global_variable_map;
    }

    /**
     * @unused-param $scope
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in the global scope.
     */
    public function getVariableMapExcludingScope(?Scope $scope): array
    {
        // Phan always generates a branch scope in front of the branch scope.
        // The global scope can have hundreds or thousands of variables in some projects, avoid merging variables from it.
        return [];
    }

    /**
     * @param Variable $variable
     * A variable to add to the local scope
     */
    public function withVariable(Variable $variable): Scope
    {
        $this->addVariable($variable);
        return $this;
    }

    public function addVariable(Variable $variable): void
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
     */
    public function addGlobalVariable(Variable $variable): void
    {
        $this->addVariable($variable);
    }

    /**
     * @return bool
     * True if a global variable with the given name exists
     */
    public function hasGlobalVariableWithName(string $name): bool
    {
        return $this->hasVariableWithName($name);
    }

    /**
     * @return Variable
     * The global variable with the given name
     */
    public function getGlobalVariableByName(string $name): Variable
    {
        return $this->getVariableByName($name);
    }

    /**
     * @return bool
     * True if this scope has a parent scope
     */
    public function hasParentScope(): bool
    {
        return false;
    }

    /**
     * @return never
     * Get the parent scope of this scope
     */
    public function getParentScope(): Scope
    {
        throw new AssertionError("Global scope has no parent scope");
    }

    /**
     * @return never
     */
    public function getClassFQSEN(): FullyQualifiedClassName
    {
        throw new AssertionError("Cannot get class FQSEN on scope");
    }

    /**
     * @return never
     */
    public function getPropertyFQSEN(): FullyQualifiedPropertyName
    {
        throw new AssertionError("Cannot get class FQSEN on scope");
    }

    /**
     * @return null
     * @suppress PhanTypeMismatchDeclaredReturnNullable
     */
    public function getClassFQSENOrNull(): ?FullyQualifiedClassName
    {
        return null;
    }

    /**
     * @return never
     */
    public function getFunctionLikeFQSEN()
    {
        throw new AssertionError("Cannot get method/function/closure FQSEN on scope");
    }

    /**
     * @unused-param $template_type_identifier
     */
    public function hasTemplateType(
        string $template_type_identifier
    ): bool {
        return false;
    }
}
