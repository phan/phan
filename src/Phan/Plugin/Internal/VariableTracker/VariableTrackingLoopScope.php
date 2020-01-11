<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal\VariableTracker;

/**
 * This is the same as VariableTrackingBranchScope, but records a level for break/continue.
 */
final class VariableTrackingLoopScope extends VariableTrackingBranchScope
{
    /**
     * @var list<VariableTrackingBranchScope>
     * The scopes that broke out early within the inner body of this loop.
     *
     * Both the variable definitions and uses of these scopes must be accounted for.
     */
    public $skipped_loop_scopes = [];

    /**
     * @var list<VariableTrackingBranchScope>
     * The scopes that exited early within the inner body of this loop
     *
     * Only the variable uses of these scopes must be accounted for.
     */
    public $skipped_exiting_loop_scopes = [];

    // inherits defs, uses

    // inherit VariableTrackingBranchScope::__construct()

    /**
     * Record a statement that was unreachable due to break/continue statements.
     *
     * @param VariableTrackingBranchScope $skipped_loop_scope
     * @param bool $exits
     */
    public function recordSkippedScope(VariableTrackingBranchScope $skipped_loop_scope, bool $exits): void
    {
        if ($exits) {
            $this->skipped_exiting_loop_scopes[] = $skipped_loop_scope;
        } else {
            $this->skipped_loop_scopes[] = $skipped_loop_scope;
        }
        // Subclasses will implement this
    }

    /**
     * Account for the definitions and uses of the child scopes with `break`/`continue` inside of this switch statement,
     * using these to update the definition and uses of the outer scope of the `switch` node
     */
    public function flattenSwitchCaseScopes(VariableGraph $graph): void
    {
        foreach ($this->skipped_loop_scopes as $alternate_scope) {
            $this->flattenScopeToMergedLoopResult($this, $alternate_scope, $graph);
        }
        foreach ($this->skipped_exiting_loop_scopes as $alternate_scope) {
            $this->flattenUsesFromScopeToMergedLoopResult($this, $alternate_scope, $graph);
        }
        $this->skipped_loop_scopes = [];
        $this->skipped_exiting_loop_scopes = [];
    }
}
