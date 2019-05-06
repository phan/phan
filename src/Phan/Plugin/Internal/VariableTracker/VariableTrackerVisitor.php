<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\VariableTracker;

use AssertionError;
use ast;
use ast\Node;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use Phan\Parse\ParseVisitor;
use function is_string;

/**
 * The planned design for this is similar to the way BlockAnalysisVisitor tracks union types of variables
 * (Tracks locations instead of union types).
 *
 * 1. Track definitions and uses, on a per-statement basis.
 * 2. Use visit*() overrides for individual element types.
 * 3. Split tracking variables into pre-analysis, recursive, and post-analysis steps
 * 4. Track based on an identifier corresponding to the \ast\Node of the assignment (e.g. using \spl_object_id())
 *
 * TODO: Improve analysis within the ternary operator (cond() ? ($x = 2) : ($x = 3);
 * TODO: Support unset
 * TODO: Fix tests/files/src/0426_inline_var_force.php
 *
 * @phan-file-suppress PhanTypeMismatchArgumentNullable child nodes as used here are non-null
 */
final class VariableTrackerVisitor extends AnalysisVisitor
{
    /**
     * This shared graph instance maps definitions of variables (in a function-like context)
     * to the uses of that variable.
     *
     * @var VariableGraph
     */
    public static $variable_graph;

    /**
     * This represents the status of variables in the current scope
     * (e.g. which variable definitions are available to be used, etc.)
     *
     * @var VariableTrackingScope
     */
    private $scope;

    /**
     * @var ?Node the most recently visited statement within the AST_STMT_LIST.
     * This can be used to check if an expression such as $x++ is used by something else.
     *
     * Tracking the parent_node_list is possible, but would be much more verbose.
     */
    private $top_level_statement;

    public function __construct(VariableTrackingScope $scope)
    {
        $this->scope = $scope;
    }

    /**
     * This is the default implementation for node types which don't have any overrides
     * @return VariableTrackingScope
     * @override
     */
    public function visit(Node $node)
    {
        foreach ($node->children as $child_node) {
            if (!($child_node instanceof Node)) {
                continue;
            }

            $this->scope = $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        }
        return $this->scope;
    }

    /**
     * This is the default implementation for node types which don't have any overrides
     * @return VariableTrackingScope
     * @override
     */
    public function visitStmtList(Node $node)
    {
        $top_level_statement = $this->top_level_statement;
        foreach ($node->children as $child_node) {
            if (!($child_node instanceof Node)) {
                continue;
            }

            // TODO: Specialize?
            $this->top_level_statement = $child_node;
            $this->scope = $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        }
        $this->top_level_statement = $top_level_statement;
        return $this->scope;
    }

    /**
     * @return VariableTrackingScope
     * @override
     */
    public function visitAssignRef(Node $node)
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            $this->markVariablesAsReference($expr);
            $this->scope = $this->analyze($this->scope, $expr);
        }
        $var_node = $node->children['var'];
        if ($var_node instanceof Node && $var_node->kind === \ast\AST_VAR) {
            $name = $var_node->children['name'];
            if (is_string($name)) {
                self::$variable_graph->recordVariableUsage($name, $var_node, $this->scope);
            }
        }
        return $this->analyzeAssignmentTarget($var_node, true, null);
    }

    private function markVariablesAsReference(Node $expr)
    {
        while (\in_array($expr->kind, [ast\AST_DIM, ast\AST_PROP], true)) {
            $expr = $expr->children['expr'];
            if (!$expr instanceof Node) {
                return;
            }
        }
        if ($expr->kind === ast\AST_VAR) {
            $name = $expr->children['name'];
            if (is_string($name)) {
                self::$variable_graph->markAsReference($name);
            }
        }
    }

    /**
     * Analyze X++
     * @override
     * @return VariableTrackingScope
     */
    public function visitPostInc(Node $node)
    {
        return $this->analyzeIncDec($node);
    }

    /**
     * Analyze X--
     * @override
     * @return VariableTrackingScope
     */
    public function visitPostDec(Node $node)
    {
        return $this->analyzeIncDec($node);
    }

    /**
     * Analyze ++X
     * @override
     * @return VariableTrackingScope
     */
    public function visitPreInc(Node $node)
    {
        return $this->analyzeIncDec($node);
    }

    /**
     * Analyze --X
     * @override
     * @return VariableTrackingScope
     */
    public function visitPreDec(Node $node)
    {
        return $this->analyzeIncDec($node);
    }

    /**
     * @return VariableTrackingScope
     */
    private function analyzeIncDec(Node $node)
    {
        $var_node = $node->children['var'];
        if ($var_node instanceof Node && $var_node->kind === ast\AST_VAR) {
            $name = $var_node->children['name'];
            if (is_string($name)) {
                // $node is the usage of this variable
                // Here, we use $node instead of $var_node as the declaration node so that recordVariableUsage won't treat increments in loops as using themselves.
                self::$variable_graph->recordVariableUsage($name, $node, $this->scope);
                if ($this->top_level_statement === $node) {
                    // And the whole inc/dec operation is the redefinition of this variable.
                    // To reduce false positives, treat `;$x++;` as a redefinition, but not `foo($x++)`
                    self::$variable_graph->recordVariableDefinition($name, $node, $this->scope, null);
                    $this->scope->recordDefinition($name, $node);
                }
                return $this->scope;
            }
        }
        return $this->visit($node);
    }

    /**
     * @return VariableTrackingScope
     * @override
     */
    public function visitAssignOp(Node $node)
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            $this->scope = $this->analyze($this->scope, $expr);
        }
        $var_node = $node->children['var'];
        if (!($var_node instanceof Node)) {
            return $this->scope;
        }
        switch ($var_node->kind) {
            case ast\AST_VAR:
                $name = $var_node->children['name'];
                if (!is_string($name)) {
                    break;
                }
                // The left-hand node ($node) is the usage of this variable
                // We use the same node id so that phan will warn about unused declarations within loops
                self::$variable_graph->recordVariableUsage($name, $node, $this->scope);
                // And the whole assignment operation is the redefinition of this variable
                self::$variable_graph->recordVariableDefinition($name, $node, $this->scope, null);
                $this->scope->recordDefinition($name, $node);
                return $this->scope;
            case ast\AST_PROP:
                return $this->analyzePropAssignmentTarget($var_node);
            case ast\AST_DIM:
                return $this->analyzeDimAssignmentTarget($var_node);
                // TODO: Analyze array access and param/return references of function/method calls.
            default:
                // Static property or an unexpected target.
                // Analyze this normally.
                return $this->analyze($this->scope, $var_node);
        }
        return $this->scope;
    }

    /**
     * @return VariableTrackingScope
     * @override
     */
    public function visitAssign(Node $node)
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            $this->scope = $this->analyze($this->scope, $expr);
        }
        return $this->analyzeAssignmentTarget($node->children['var'], false, self::getConstExprOrNull($expr));
    }

    /**
     * @param Node|string|int|float $expr
     * @return Node|string|int|float|null
     */
    private static function getConstExprOrNull($expr) {
        return ParseVisitor::isConstExpr($expr) ? $expr : null;
    }

    /**
     * @param Node|int|string|float|null $node
     * @param Node|int|string|float|null $const_expr
     */
    private function analyzeAssignmentTarget($node, bool $is_ref, $const_expr) : VariableTrackingScope
    {
        // TODO: Push onto the node list?
        if (!($node instanceof Node)) {
            return $this->scope;
        }
        switch ($node->kind) {
            case ast\AST_VAR:
                $name = $node->children['name'];
                if (!is_string($name)) {
                    break;
                }
                if ($is_ref) {
                    self::$variable_graph->markAsReference($name);
                }
                self::$variable_graph->recordVariableDefinition($name, $node, $this->scope, $const_expr);
                $this->scope->recordDefinition($name, $node);
                return $this->scope;
            case ast\AST_ARRAY:
                return $this->analyzeArrayAssignmentTarget($node, $const_expr);

            case ast\AST_REF:
                return $this->analyzeAssignmentTarget($node->children['var'], true, null);
            case ast\AST_PROP:
                return $this->analyzePropAssignmentTarget($node);
            case ast\AST_DIM:
                return $this->analyzeDimAssignmentTarget($node);
                // TODO: Analyze array access and param/return references of function/method calls.
            default:
                // Static property or an unexpected target.
                // Analyze this normally.
                return $this->analyze($this->scope, $node);
        }
        return $this->scope;
    }

    /**
     * @param Node|int|string|float|null $const_expr
     */
    private function analyzeArrayAssignmentTarget(Node $node, $const_expr) : VariableTrackingScope
    {
        foreach ($node->children as $elem_node) {
            if (!($elem_node instanceof Node)) {
                continue;
            }
            // Treat $key in `[$key => $y] = $array` as a usage of $key
            $this->scope = $this->analyzeWhenValidNode($this->scope, $elem_node->children['key']);
            $this->scope = $this->analyzeAssignmentTarget($elem_node->children['value'], false, $const_expr);
        }
        return $this->scope;
    }

    private function analyzePropAssignmentTarget(Node $node) : VariableTrackingScope
    {
        // Treat $y in `$x->$y = $z;` as a usage of $y
        $this->scope = $this->analyzeWhenValidNode($this->scope, $node->children['prop']);
        $expr = $node->children['expr'];
        if ($expr instanceof Node && $expr->kind === \ast\AST_VAR) {
            $name = $expr->children['name'];
            if (is_string($name)) {
                // treat $x->prop = 2 like a usage of $x
                self::$variable_graph->recordVariableUsage($name, $expr, $this->scope);
                self::$variable_graph->recordVariableModification($name);
            }
        }
        return $this->analyzeWhenValidNode($this->scope, $expr);  // lower false positives by not treating this as a definition
        // // treat $x->prop = 2 like a definition to $x (in addition to having treated this as a usage)
        // return $this->analyzeAssignmentTarget($expr, false);
    }

    private function analyzeDimAssignmentTarget(Node $node) : VariableTrackingScope
    {
        // Treat $y in `$x[$y] = $z;` as a usage of $y
        $this->scope = $this->analyzeWhenValidNode($this->scope, $node->children['dim']);
        $expr = $node->children['expr'];
        while ($expr instanceof Node) {
            if ($expr->kind === \ast\AST_VAR) {
                $name = $expr->children['name'];
                if (is_string($name)) {
                    // treat $x['dim_name'] = 2 like a usage of $x
                    //
                    // TODO: More aggressively warn if there is only a single dimension to $x
                    self::$variable_graph->recordVariableUsage($name, $expr, $this->scope);
                    self::$variable_graph->recordVariableModification($name);
                }
                break;
            } elseif (\in_array($expr->kind, [ast\AST_DIM, ast\AST_PROP], true)) {
                $expr = $expr->children['expr'];
            } else {
                break;
            }
        }
        return $this->analyzeWhenValidNode($this->scope, $node->children['expr']);  // lower false positives by not treating this as a definition
        // // treat $x['dim_name'] = 2 like a definition to $x (in addition to having treated this as a usage)
        // return $this->analyzeAssignmentTarget($expr, false);
    }

    /**
     * @return VariableTrackingScope
     */
    public function handleMissingNodeKind(Node $unused_node)
    {
        // do nothing
        return $this->scope;
    }

    /**
     * @param Node|string|int|float|null $child_node
     * @return VariableTrackingScope
     */
    private function analyzeWhenValidNode(VariableTrackingScope $scope, $child_node)
    {
        if ($child_node instanceof Node) {
            return $this->analyze($scope, $child_node);
        }
        return $scope;
    }

    /**
     * This is an abstraction for getting a new, updated context for a child node.
     *
     * @param Node $child_node - The node which will be analyzed to create the updated context.
     * @return VariableTrackingScope
     */
    private function analyze(VariableTrackingScope $scope, Node $child_node)
    {
        // Modify the original object instead of creating a new BlockAnalysisVisitor.
        // this is slightly more efficient, especially if a large number of unchanged parameters would exist.
        $old_scope = $this->scope;
        $this->scope = $scope;
        try {
            return $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        } finally {
            $this->scope = $old_scope;
        }
    }

    /**
     * Do not recurse into function declarations within a scope
     * @return VariableTrackingScope
     * @override
     */
    public function visitFuncDecl(Node $unused_node)
    {
        return $this->scope;
    }

    /**
     * Do not recurse into class declarations within a scope
     * @return VariableTrackingScope
     * @override
     */
    public function visitClass(Node $unused_node)
    {
        return $this->scope;
    }

    /**
     * Do not recurse into closure declarations within a scope.
     *
     * FIXME: Check closure use variables without checking statements
     * @return VariableTrackingScope
     * @override
     */
    public function visitClosure(Node $node)
    {
        foreach ($node->children['uses']->children ?? [] as $closure_use) {
            if (!($closure_use instanceof Node)) {
                continue;
            }

            $name = $closure_use->children['name'];
            if (!is_string($name)) {
                continue;
            }

            if ($closure_use->flags & ast\flags\PARAM_REF) {
                self::$variable_graph->recordVariableDefinition($name, $closure_use, $this->scope, null);
                self::$variable_graph->markAsReference($name);
            } else {
                self::$variable_graph->recordVariableUsage($name, $closure_use, $this->scope);
            }
        }
        return $this->scope;
    }

    /**
     * @override
     * @return VariableTrackingScope
     * Common no-op
     */
    public function visitName(Node $unused_node)
    {
        return $this->scope;
    }

    /**
     * TODO: Check if the current context is a function call passing an argument by reference
     * @return VariableTrackingScope
     * @override
     */
    public function visitVar(Node $node)
    {
        $name = $node->children['name'];
        if (\is_string($name)) {
            self::$variable_graph->recordVariableUsage($name, $node, $this->scope);
            if ($node === $this->top_level_statement) {
                self::$variable_graph->recordVariableDefinition($name, $node, $this->scope, null);
                $this->scope->recordDefinition($name, $node);
            }
        } elseif ($name instanceof Node) {
            return $this->analyze($this->scope, $name);
        }
        return $this->scope;
    }

    /**
     * TODO: Check if the current context is a function call passing an argument by reference
     * @return VariableTrackingScope
     * @override
     */
    public function visitStatic(Node $node)
    {
        $name = $node->children['var']->children['name'] ?? null;
        if (\is_string($name)) {
            self::$variable_graph->markAsStaticVariable($name);
            self::$variable_graph->recordVariableDefinition($name, $node, $this->scope, null);
            $this->scope->recordDefinition($name, $node);
        }
        return $this->scope;
    }

    /**
     * TODO: Check if the current context is a function call passing an argument by reference
     * @return VariableTrackingScope
     * @override
     */
    public function visitGlobal(Node $node)
    {
        $name = $node->children['var']->children['name'] ?? null;
        if (\is_string($name)) {
            self::$variable_graph->markAsGlobalVariable($name);
        }
        return $this->scope;
    }

    /**
     * Analyzes `foreach ($expr as $key => $value) { stmts }
     * @return VariableTrackingScope
     */
    public function visitForeach(Node $node)
    {
        $expr_node = $node->children['expr'];
        $outer_scope_unbranched = $this->analyzeWhenValidNode($this->scope, $expr_node);
        $outer_scope = new VariableTrackingBranchScope($outer_scope_unbranched);

        // Replace the scope with the inner scope
        $this->scope = new VariableTrackingLoopScope($outer_scope);

        $key_node = $node->children['key'];
        $this->scope = $this->analyzeAssignmentTarget($key_node, false, null);

        $value_node = $node->children['value'];
        if (isset($key_node)) {
            self::$variable_graph->markAsLoopValueNode($value_node);
        }
        $this->scope = $this->analyzeAssignmentTarget($value_node, false, null);  // analyzeAssignmentTarget checks for AST_REF

        // TODO: Update graph: inner loop definitions can be used inside the loop.
        // TODO: Create a branchScope? - Loop iterations can run 0 times.
        $inner_scope = $this->analyze($this->scope, $node->children['stmts']);

        // Merge inner scope into outer scope
        // @phan-suppress-next-line PhanTypeMismatchArgument
        $outer_scope = $outer_scope->mergeInnerLoopScope($inner_scope, self::$variable_graph);

        return $outer_scope_unbranched->mergeWithSingleBranchScope($outer_scope);
    }

    /**
     * Analyzes `while (cond) { stmts }`
     * @return VariableTrackingScope
     * @override
     */
    public function visitWhile(Node $node)
    {
        $outer_scope_unbranched = $this->analyzeWhenValidNode($this->scope, $node->children['cond']);
        $outer_scope = new VariableTrackingBranchScope($outer_scope_unbranched);

        $inner_scope = new VariableTrackingLoopScope($outer_scope);
        $inner_scope = $this->analyze($inner_scope, $node->children['stmts']);
        $inner_scope = $this->analyzeWhenValidNode($inner_scope, $node->children['cond']);
        '@phan-var VariableTrackingLoopScope $inner_scope';

        // Merge inner scope into outer scope
        $outer_scope = $outer_scope->mergeInnerLoopScope($inner_scope, self::$variable_graph);
        return $outer_scope_unbranched->mergeWithSingleBranchScope($outer_scope);
    }

    /**
     * Analyzes `do { stmts } while (cond);`
     *
     * TODO: Fix https://github.com/phan/phan/issues/2029
     *
     * @param Node $node a node of type AST_DO_WHILE
     * @return VariableTrackingScope
     * @override
     */
    public function visitDoWhile(Node $node)
    {
        $outer_scope_unbranched = $this->scope;
        $outer_scope = new VariableTrackingBranchScope($outer_scope_unbranched);

        $inner_scope = new VariableTrackingLoopScope($outer_scope);
        $inner_scope = $this->analyze($inner_scope, $node->children['stmts']);
        $inner_scope = $this->analyzeWhenValidNode($inner_scope, $node->children['cond']);
        '@phan-var VariableTrackingLoopScope $inner_scope';

        // Merge inner scope into outer scope
        $outer_scope = $outer_scope->mergeInnerLoopScope($inner_scope, self::$variable_graph);
        '@phan-var VariableTrackingLoopScope $inner_scope';
        return $outer_scope_unbranched->mergeWithSingleBranchScope($outer_scope);
    }

    /**
     * Analyzes `for (init; cond; loop) { stmts }`
     * @param Node $node a node of type AST_FOR
     * @return VariableTrackingScope
     * @override
     */
    public function visitFor(Node $node)
    {
        $top_level_statement = $this->top_level_statement;
        $init_node = $node->children['init'];
        if ($init_node instanceof Node) {
            $this->top_level_statement = $init_node;
            $outer_scope_unbranched = $this->analyze($this->scope, $init_node);
        } else {
            $outer_scope_unbranched = $this->scope;
        }
        $outer_scope_unbranched = $this->analyzeWhenValidNode($outer_scope_unbranched, $node->children['cond']);
        $outer_scope = new VariableTrackingBranchScope($outer_scope_unbranched);

        $inner_scope = new VariableTrackingLoopScope($outer_scope);
        // Iterate over the nodes in AST_EXPR_LIST `loop` for `for (init; cond; loop)` to check their uses and definitions of variables
        foreach ($node->children['loop']->children ?? [] as $loop_node) {
            if (!($loop_node instanceof Node)) {
                continue;
            }
            $this->top_level_statement = $loop_node;
            $loop_scope = $this->analyze(new VariableTrackingBranchScope($inner_scope), $loop_node);
            // @phan-suppress-next-line PhanTypeMismatchArgument
            $inner_scope = $inner_scope->mergeWithSingleBranchScope($loop_scope);
            $this->top_level_statement = $top_level_statement;
        }
        // TODO: If the graph analysis is improved, look into making this stop analyzing 'loop' twice
        $inner_scope = $this->analyzeWhenValidNode($inner_scope, $node->children['cond']);
        $inner_scope = $this->analyze($inner_scope, $node->children['stmts']);
        foreach ($node->children['loop']->children ?? [] as $loop_node) {
            if ($loop_node instanceof Node) {
                $loop_scope = $this->analyze(new VariableTrackingBranchScope($inner_scope), $loop_node);
                // @phan-suppress-next-line PhanTypeMismatchArgument
                $inner_scope = $inner_scope->mergeWithSingleBranchScope($loop_scope);
            }
        }

        // Merge inner scope into outer scope
        // @phan-suppress-next-line PhanTypeMismatchArgument
        $outer_scope = $outer_scope->mergeInnerLoopScope($inner_scope, self::$variable_graph);
        return $outer_scope_unbranched->mergeWithSingleBranchScope($outer_scope);
    }

    /**
     * Analyzes if statements.
     *
     * @param Node $node a node of kind AST_IF
     * @return VariableTrackingScope
     *
     * @see BlockAnalysisVisitor::visitIf()
     * @override
     */
    public function visitIf(Node $node)
    {
        $outer_scope = $this->scope;

        $inner_scope_list = [];
        $merge_parent_scope = true;
        foreach ($node->children as $if_node) {
            if (!($if_node instanceof Node)) {
                // impossible
                continue;
            }
            // Replace the scope with the inner scope
            // Analyzing if_node->children['cond'] should affect $outer_scope.
            // This isn't precise, and doesn't fully understand assignments within conditions.
            $cond_node = $if_node->children['cond'];
            $stmts_node = $if_node->children['stmts'];

            if ($cond_node instanceof Node) {
                $inner_cond_scope = new VariableTrackingBranchScope($outer_scope);
                $inner_cond_scope = $this->analyze($inner_cond_scope, $cond_node);
                '@phan-var VariableTrackingBranchScope $inner_cond_scope';
                $outer_scope = $outer_scope->mergeBranchScopeList([$inner_cond_scope], $merge_parent_scope, []);
            }

            $inner_scope = new VariableTrackingBranchScope($outer_scope);
            $inner_scope = $this->analyze($inner_scope, $stmts_node);

            '@phan-var VariableTrackingBranchScope $inner_scope';

            if (BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($stmts_node)) {
                $exits = BlockExitStatusChecker::willUnconditionallyThrowOrReturn($stmts_node);
                $outer_scope->recordSkippedScope($inner_scope, $exits);
            } else {
                $inner_scope_list[] = $inner_scope;
            }
            if ($cond_node === null || (\is_scalar($cond_node) && $cond_node)) {
                $merge_parent_scope = false;
            }
        }

        // Merge inner scope into outer scope
        return $outer_scope->mergeBranchScopeList($inner_scope_list, $merge_parent_scope, []);
    }

    /**
     * Analyzes switch statements.
     *
     * @param Node $node a node of kind AST_SWITCH
     * @return VariableTrackingScope
     *
     * @override
     */
    public function visitSwitchList(Node $node)
    {
        $outer_scope = $this->scope;

        $inner_scope_list = [];
        $inner_exiting_scope_list = [];
        $merge_parent_scope = true;
        foreach ($node->children as $i => $case_node) {
            if (!($case_node instanceof Node)) {
                throw new AssertionError("Expected case statements to be nodes");
            }
            $cond_node = $case_node->children['cond'];
            $stmts_node = $case_node->children['stmts'];

            if ($cond_node instanceof Node) {
                // Analyzing if_node->children['cond'] should affect $outer_scope.
                // `switch(cond() { case $x = something(): case 3: use($x); }` is valid code.
                $outer_scope = $this->analyze($outer_scope, $cond_node);
            } elseif ($cond_node === null) {
                // this has a default, the case statements are comprehensive
                $merge_parent_scope = false;
            }

            // Skip over empty case statements (incomplete heuristic), TODO: test
            if (\count($stmts_node->children ?? []) !== 0 || $i === \count($node->children) - 1) {
                $inner_scope = new VariableTrackingLoopScope($outer_scope);
                $inner_scope = $this->analyze($inner_scope, $stmts_node);
                // Merge $inner_scope->skipped_loop_scopes
                '@phan-var VariableTrackingLoopScope $inner_scope';
                $inner_scope->flattenSwitchCaseScopes(self::$variable_graph);

                if (BlockExitStatusChecker::willUnconditionallyThrowOrReturn($stmts_node)) {
                    $inner_exiting_scope_list[] = $inner_scope;
                } else {
                    $inner_scope_list[] = $inner_scope;
                }
            }
        }

        // Merge inner scope into outer scope
        return $outer_scope->mergeBranchScopeList($inner_scope_list, $merge_parent_scope, $inner_exiting_scope_list);
    }

    /**
     * Analyzes try nodes and their catch statement lists and finally blocks.
     *
     * @param Node $node a node of kind AST_TRY
     * @return VariableTrackingScope
     *
     * @override
     */
    public function visitTry(Node $node)
    {
        $outer_scope = $this->scope;

        $try_scope = new VariableTrackingBranchScope($outer_scope);
        $try_scope = $this->analyze($try_scope, $node->children['try']);
        '@phan-var VariableTrackingBranchScope $try_scope';

        // TODO: Use BlockExitStatusChecker, like BlockAnalysisVisitor
        // TODO: Optimize
        $main_scope = $outer_scope->mergeWithSingleBranchScope($try_scope);

        $catch_node_list = $node->children['catches']->children;
        if (\count($catch_node_list) > 0) {
            $catches_scope = new VariableTrackingBranchScope($main_scope);
            $catches_scope = $this->analyze($catches_scope, $node->children['catches']);
            // @phan-suppress-next-line PhanTypeMismatchArgument
            $main_scope = $main_scope->mergeWithSingleBranchScope($catches_scope);
        }
        $finally_node = $node->children['finally'];
        if ($finally_node !== null) {
            return $this->analyze($main_scope, $finally_node);
        }
        return $main_scope;
    }

    /**
     * Analyzes catch statement lists.
     * @param Node $node a node of kind AST_CATCH_LIST
     * @return VariableTrackingScope
     *
     * @override
     */
    public function visitCatchList(Node $node)
    {
        $outer_scope = $this->scope;

        $inner_scope_list = [];
        foreach ($node->children as $catch_node) {
            if (!($catch_node instanceof Node)) {
                // impossible
                continue;
            }
            // Replace the scope with the inner scope
            // TODO: Analyzing if_node->children['cond'] should affect $outer_scope?
            $inner_scope = new VariableTrackingBranchScope($outer_scope);
            $inner_scope = $this->analyze($inner_scope, $catch_node);
            $inner_scope_list[] = $inner_scope;
        }

        // Merge inner scope into outer scope
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        return $outer_scope->mergeBranchScopeList($inner_scope_list, false, []);
    }

    /**
     * Analyzes catch statement lists.
     * @param Node $node a node of kind AST_CATCH
     * @return VariableTrackingScope
     *
     * @override
     */
    public function visitCatch(Node $node)
    {
        $var_node = $node->children['var'];

        $scope = $this->scope;
        if ($var_node->kind === \ast\AST_VAR) {
            $name = $var_node->children['name'];
            if (is_string($name)) {
                self::$variable_graph->recordVariableDefinition($name, $var_node, $scope, null);
                self::$variable_graph->markAsCaughtException($var_node);
                $scope->recordDefinition($name, $var_node);
            }
        }
        return $this->analyze($scope, $node->children['stmts']);
    }
}
