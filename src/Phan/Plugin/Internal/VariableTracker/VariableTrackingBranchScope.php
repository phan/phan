<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

/**
 * This will represent a variable scope, similar to \Phan\Language\Scope.
 * Instead of tracking the union types for variable names, this will instead track definitions of variable names.
 */
class VariableTrackingBranchScope extends VariableTrackingScope
{
    /**
     * @var VariableTrackingScope the parent of this branch scope.
     * Definitions will be merged later on.
     */
    public $parent_scope;

    // inherits defs, uses

    public function __construct(VariableTrackingScope $parent_scope)
    {
        $this->parent_scope = $parent_scope;
    }

    /**
     * @return ?array<int,true>
     * @override
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

    /**
     * Record a statement that was unreachable due to break/continue statements.
     *
     * @param VariableTrackingBranchScope $inner_scope @phan-unused-param
     * @param bool $exits true if this branch will exit.
     *             This would mean that the branch uses variables, but does not define them outside of that scope.
     *
     * @return void
     * @override
     */
    public function recordSkippedScope(VariableTrackingBranchScope $inner_scope, bool $exits)
    {
        $this->parent_scope->recordSkippedScope($inner_scope, $exits);
    }
}
