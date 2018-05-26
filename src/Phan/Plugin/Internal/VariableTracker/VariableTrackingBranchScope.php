<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

/**
 * This will represent a variable scope, similar to \Phan\Language\Scope.
 * Instead of tracking the union types for variable names, this will instead track definitions of variable names.
 */
final class VariableTrackingBranchScope extends VariableTrackingScope
{
    /**
     * @var VariableTrackingScope the parent of this branch scope.
     * Definitions will be merged later on.
     */
    public $parent_scope;

    // inherits defs, uses

    public function __construct(VariableTrackingScope $parent_scope) {
        $this->parent_scope = $parent_scope;
    }

    /**
     * @return ?array<int,true>
     */
    public function getDefinition(string $variable_name)
    {
        $parent_definitions = $this->parent_scope->getDefinition($variable_name);
        $definitions = $this->defs[$variable_name] ?? null;
        if ($parent_definitions === null) {
            return $definitions;
        }
        if ($definitions === null) {
            return $parent_definitions;
        }
        $definitions += $parent_definitions;
        return $definitions;
    }
}
