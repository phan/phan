<?php

declare(strict_types=1);

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
     * @return ?associative-array<int,true>
     * @override
     */
    public function getDefinition(string $variable_name): ?array
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
     * @return ?associative-array<int,true>
     * @override
     */
    public function getDefinitionUpToScope(string $variable_name, VariableTrackingScope $forbidden_scope): ?array
    {
        if ($this === $forbidden_scope) {
            return null;
        }
        return $this->defs[$variable_name] ?? $this->parent_scope->getDefinitionUpToScope($variable_name, $forbidden_scope);
    }

    /**
     * @return array<string,associative-array<int,true>>
     */
    public function getDefinitionsRecursively(): array
    {
        $defs = $this->parent_scope->getDefinitionsRecursively();
        foreach ($this->defs as $variable_name => $def) {
            // TODO: Distinguish between being defined in *some* cases and being defined in *all* cases in this branch
            $defs[$variable_name] = $def;
        }
        return $defs;
    }

    /**
     * inherit definitions from outer scope.
     * Used to analyze fallthrough in switch statements.
     */
    public function inheritDefsFromOuterScope(VariableTrackingScope $outer_scope): void
    {
        foreach ($this->defs as $variable_name => $_) {
            $outer_defs = $outer_scope->getDefinition((string)$variable_name);
            if ($outer_defs) {
                $this->defs[$variable_name] += $outer_defs;
            }
        }
    }


    /**
     * Record a statement that was unreachable due to break/continue statements.
     *
     * @param VariableTrackingBranchScope $inner_scope @phan-unused-param
     * @param bool $exits true if this branch will exit.
     *             This would mean that the branch uses variables, but does not define them outside of that scope.
     * @override
     */
    public function recordSkippedScope(VariableTrackingBranchScope $inner_scope, bool $exits): void
    {
        $this->parent_scope->recordSkippedScope($inner_scope, $exits);
    }
}
