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
        $definitions = $this->defs[$variable_name] ?? null;
        if ($definitions === null) {
            $parent_definitions = $this->parent_scope->getDefinition($variable_name);
            if (\is_array($parent_definitions)) {
                $this->defs[$variable_name] = $parent_definitions;
            }
            return $parent_definitions;
        }
        return $definitions;
    }

    /**
     * @return ?array<int,true>
     * @override
     */
    public function getDefinitionUpToScope(string $variable_name, VariableTrackingScope $forbidden_scope)
    {
        if ($this === $forbidden_scope) {
            return null;
        }
        return $this->defs[$variable_name] ?? $this->parent_scope->getDefinitionUpToScope($variable_name, $forbidden_scope);
    }

    /**
     * @return array<string,array<int,true>>
     */
    public function getDefinitionsRecursively()
    {
        $defs = $this->parent_scope->getDefinitionsRecursively();
        foreach ($this->defs as $variable_name => $def) {
            // TODO: Distinguish between being defined in *some* cases and being defined in *all* cases in this branch
            $defs[$variable_name] = $def;
        }
        return $defs;
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
