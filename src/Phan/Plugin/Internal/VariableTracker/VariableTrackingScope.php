<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

use ast\Node;

use function spl_object_id;

/**
 * This will represent a variable scope, similar to \Phan\Language\Scope.
 * Instead of tracking the union types for variable names, this will instead track definitions of variable names.
 *
 * @see ContextMergeVisitor for something similar for union types.
 */
class VariableTrackingScope
{
    /**
     * @var array<string,array<int,bool>>
     * Maps a variable id to a list of definitions in that scope.
     *
     * This is true if 100% of the definitions are made within the scope,
     * false if a fraction of the definitions could be from preceding scopes.
     *
     * TODO: Actually set the inner value to false appropriately.
     */
    protected $defs = [];

    /**
     * @var array<string,array<int,true>>
     * Maps a variable id to a list of uses which occurred within that scope.
     * (of definitions that might have occurred before this scope)
     */
    protected $uses = [];

    public function recordDefinition(string $variable_name, Node $node)
    {
        // Create a new definition for variable_name.
        // Replace the definitions for $variable_name.
        $this->defs[$variable_name] = [spl_object_id($node) => true];
    }

    public function recordDefinitionById(string $variable_name, int $node_id)
    {
        // Create a new definition for variable_name.
        // Replace the definitions for $variable_name.
        $this->defs[$variable_name] = [$node_id => true];
    }

    /**
     * @suppress PhanUnreferencedPublicMethod used by reference
     */
    public function recordUsage(string $variable_name, Node $node)
    {
        $node_id = spl_object_id($node);
        // Create a new usage for variable_name.

        if (($this->defs[$variable_name][$node_id] ?? false) !== true) {
            $this->uses[$variable_name][$node_id] = true;
        }
    }

    public function recordUsageById(string $variable_name, int $node_id)
    {
        // Create a new usage for variable_name.
        if (($this->defs[$variable_name][$node_id] ?? false) !== true) {
            $this->uses[$variable_name][$node_id] = true;
        }
    }

    /**
     * Overridden by subclasses
     * @return ?array<int,true>
     */
    public function getDefinition(string $variable_name)
    {
        return $this->defs[$variable_name] ?? null;
    }

    public function mergeInnerLoopScope(
        VariableTrackingLoopScope $scope,
        VariableGraph $graph
    ) : VariableTrackingScope {
        $result = clone($this);
        // TODO: Can this be optimized for common use cases?
        foreach ($scope->skipped_loop_scopes as $alternate_scope) {
            $this->flattenScopeToMergedLoopResult($scope, $alternate_scope, $graph);
        }
        foreach ($scope->skipped_exiting_loop_scopes as $alternate_scope) {
            $this->flattenUsesFromScopeToMergedLoopResult($scope, $alternate_scope, $graph);
        }
        $this->addScopeToMergedLoopResult($result, $scope, $graph);
        return $result;
    }

    /**
     * @return void
     */
    protected function flattenScopeToMergedLoopResult(
        VariableTrackingLoopScope $scope,
        VariableTrackingBranchScope $alternate_scope,
        VariableGraph $graph
    ) {
        // Need to flatten these to the same level
        // The LoopScope might have been cloned, so just keep going until the closest loop scope.
        // TODO: Look at this, see if this way of merging definitions and usages will miss any false positives
        $parent_scope = $alternate_scope->parent_scope;
        if (!($parent_scope instanceof VariableTrackingLoopScope)) {
            '@phan-var VariableTrackingBranchScope $parent_scope';
            $this->flattenScopeToMergedLoopResult($scope, $parent_scope, $graph);
        }
        $this->addScopeToMergedLoopResult($scope, $alternate_scope, $graph);
        $scope->mergeUses($alternate_scope->uses);
    }

    /**
     * @return void
     */
    protected function flattenUsesFromScopeToMergedLoopResult(
        VariableTrackingLoopScope $scope,
        VariableTrackingBranchScope $alternate_scope,
        VariableGraph $graph
    ) {
        // Need to flatten these to the same level
        // The LoopScope might have been cloned, so just keep going until the closest loop scope.
        // TODO: Look at this, see if this way of merging definitions and usages will miss any false positives
        $parent_scope = $alternate_scope->parent_scope;
        if (!($parent_scope instanceof VariableTrackingLoopScope)) {
            '@phan-var VariableTrackingBranchScope $parent_scope';
            $this->flattenScopeToMergedLoopResult($scope, $parent_scope, $graph);
        }
        $scope->mergeUses($alternate_scope->uses);
    }

    /**
     * @return void
     */
    private function addScopeToMergedLoopResult(
        VariableTrackingScope $result,
        VariableTrackingBranchScope $scope,
        VariableGraph $graph
    ) {
        foreach ($scope->defs as $variable_name => $defs) {
            $defs_for_variable = $result->defs[$variable_name] ?? [];
            $loop_uses_of_own_variable = $scope->uses[$variable_name] ?? null;

            foreach ($defs as $def_id => $_) {
                if ($loop_uses_of_own_variable) {
                    $graph->recordLoopSelfUsage($variable_name, $def_id, $loop_uses_of_own_variable);
                }
                $defs_for_variable[$def_id] = true;
            }
            $result->defs[$variable_name] = $defs_for_variable;
        }
        $result->mergeUses($scope->uses);
    }

    /**
     * @param array<string,array<int,bool>> $uses
     */
    private function mergeUses(array $uses)
    {
        foreach ($uses as $variable_name => $def_id_set) {
            if (!isset($this->uses[$variable_name])) {
                $this->uses[$variable_name] = $def_id_set;
                continue;
            }
            $this->uses[$variable_name] += $def_id_set;
        }
    }

    /**
     * @param array<int,VariableTrackingBranchScope> $branch_scopes
     * @param array<int,VariableTrackingBranchScope> $inner_exiting_scope_list
     */
    public function mergeBranchScopeList(
        array $branch_scopes,
        bool $merge_parent_scope,
        array $inner_exiting_scope_list
    ) : VariableTrackingScope {
        // Compute the keys which were redefined in branch scopes
        // TODO: Optimize
        $result = clone($this);
        $def_key_set = [];
        foreach ($branch_scopes as $scope) {
            foreach ($scope->defs as $variable_name => $_) {
                $def_key_set[$variable_name] = true;
            }
            // Anything which is used within a branch is used within the parent
            $result->mergeUses($scope->uses);
        }
        foreach ($inner_exiting_scope_list as $scope) {
            // TODO: Make this properly recurse until it reaches the right depth (unnecessary right now)
            $result->mergeUses($scope->uses);
        }
        if ($merge_parent_scope) {
            $is_redefined_in_all_scopes = function (string $variable_name) use ($branch_scopes) : bool {
                foreach ($branch_scopes as $scope) {
                    if (isset($scope->defs[$variable_name])) {
                        return false;
                    }
                }
                return true;
            };
        } else {
            $is_redefined_in_all_scopes = function (string $unused_variable_name) : bool {
                return false;
            };
        }
        foreach ($def_key_set as $variable_name => $_) {
            if ($is_redefined_in_all_scopes($variable_name)) {
                $defs_for_variable = [];
            } else {
                $defs_for_variable = $result->defs[$variable_name] ?? [];
            }

            foreach ($branch_scopes as $scope) {
                foreach ($scope->defs[$variable_name] ?? [] as $def_id => $_) {
                    $defs_for_variable[$def_id] = true;
                }
            }
            $result->defs[$variable_name] = $defs_for_variable;
        }
        return $result;
    }

    /**
     * Record a statement that was unreachable due to break/continue statements.
     *
     * @param VariableTrackingBranchScope $inner_scope @phan-unused-param
     * @param bool $exits true if the branch of $inner_scope will exit. @phan-unused-param
     *             This would mean that the branch uses variables, but does not define them outside of that scope.
     * @return void
     */
    public function recordSkippedScope(VariableTrackingBranchScope $inner_scope, bool $exits)
    {
        // Subclasses will implement this
    }
}
