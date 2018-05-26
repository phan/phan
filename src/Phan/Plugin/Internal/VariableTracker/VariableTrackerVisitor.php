<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use ast\Node;
use ast;  // TODO: Figure out why Phan isn't warning about Phan\Plugin\Internal\VariableTracker\ast\AST_VAR without this.

use function is_string;

/**
 * The planned design for this is similar to the way BlockAnalysisVisitor tracks union types of variables
 * (Tracks locations instead of union types).
 *
 * 1. Track definitions and uses, on a per-statement basis.
 * 2. Use visit*() overrides for individual element types.
 * 3. Split tracking variables into pre-analysis, recursive, and post-analysis steps
 * 4. Track based on an identifier corresponding to the \ast\Node of the assignment (e.g. using \spl_object_id())
 */
final class VariableTrackerVisitor extends AnalysisVisitor
{
    /**
     * @var VariableGraph
     */
    public static $variable_graph;

    /** @var VariableTrackingScope */
    private $scope;

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
        foreach ($node->children as $child_node) {
            if (!($child_node instanceof Node)) {
                continue;
            }

            // TODO: Specialize?
            $this->scope = $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        }
        return $this->scope;
    }

    /**
     * @return VariableTrackingScope
     * @override
     */
    public function visitAssignRef(Node $node)
    {
        return $this->analyzeAssign($node, true);
    }


    /**
     * @return VariableTrackingScope
     * @override
     */
    public function visitAssign(Node $node)
    {
        return $this->analyzeAssign($node, false);
    }

    /**
     * @return VariableTrackingScope
     */
    private function analyzeAssign(Node $node, bool $is_ref)
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            $this->scope = $this->analyze($this->scope, $expr);
        }
        return $this->analyzeAssignmentTarget($node->children['var'], $is_ref);
    }

    private function analyzeAssignmentTarget($node, bool $is_ref) : VariableTrackingScope
    {
        // TODO: Push onto the node list?
        if (!($node instanceof Node)) {
            return $this->scope;
        }
        $kind = $node->kind;
        switch ($kind) {
            case ast\AST_VAR:
                $name = $node->children['name'];
                if (!is_string($name)) {
                    break;
                }
                if ($is_ref) {
                    self::$variable_graph->markAsReference($name);
                }
                self::$variable_graph->recordVariableDefinition($name, $node, $this->scope);
                $this->scope->recordDefinition($name, $node);
                break;
            case ast\AST_REF:
                $this->scope = $this->analyzeAssignmentTarget($node->children['var'], true);
                break;
            case ast\AST_PROP:
                return $this->analyzePropAssignment($node);
                // TODO: Analyze array access and param/return references of function/method calls.
        }
        return $this->scope;
    }

    private function analyzePropAssignment(Node $node) : VariableTrackingScope
    {
        // Treat $y in `$x->$y = $z;` as a usage of $y
        $this->scope = $this->analyzeWhenValidNode($this->scope, $node->children['prop']);
        $expr = $node->children['expr'];
        if ($expr instanceof Node && $expr->kind === \ast\AST_VAR) {
            $name = $expr->children['name'];
            if (is_string($name)) {
                // treat $x->prop = 2 like a usage of $x
                self::$variable_graph->recordVariableUsage($name, $expr, $this->scope);
            }
        }
        // treat $x->prop = 2 like a definition to $x (in addition to having treated this as a usage)
        return $this->analyzeAssignmentTarget($expr, false);
    }

    /**
     * @return VariableTrackingScope
     */
    public function handleMissingNodeKind(Node $node)
    {
        // do nothing
        return $this->scope;
    }

    /**
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
            $this->scope = $scope;
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
    public function visitClosure(Node $unused_node)
    {
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
            // TODO: Determine if the given usage is an assignment, a definition, or both (modifying by reference, $x++, etc.
            // See the way this is done in BlockAnalysisVisitor
        } elseif ($name instanceof Node) {
            return $this->analyze($this->scope, $name);
        }
        return $this->scope;
    }

    /**
     * Analyzes `foreach ($expr as $key => $value) { stmts }
     * @return VariableTrackingScope
     */
    public function visitForeach(Node $node) {
        $expr_node = $node->children['expr'];
        $outer_scope = $this->analyzeWhenValidNode($this->scope, $expr_node);

        // Replace the scope with the inner scope
        $this->scope = new VariableTrackingBranchScope($outer_scope);

        $key_node = $node->children['key'];
        $this->scope = $this->analyzeAssignmentTarget($key_node, false);

        $value_node = $node->children['value'];
        $this->scope = $this->analyzeAssignmentTarget($value_node, false);  // analyzeAssignmentTarget checks for AST_REF

        // TODO: Update graph: inner loop definitions can be used inside the loop.
        // TODO: Create a branchScope? - Loop iterations can run 0 times.
        $inner_scope = $this->analyze($this->scope, $node->children['stmts']);

        // Merge inner scope into outer scope
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        return $outer_scope->mergeInnerLoopScope($this->scope, self::$variable_graph);
    }

    /**
     * @param Node $node a node of kind AST_IF
     * Analyzes if statements.
     * @return VariableTrackingScope
     *
     * @see BlockAnalysisVisitor->visitIf (TODO: Use BlockExitStatusChecker)
     */
    public function visitIf(Node $node) {
        $outer_scope = $this->scope;

        $inner_scope_list = [];
        $merge_parent_scope = true;
        foreach ($node->children as $if_node) {
            if (!($if_node instanceof Node)) {
                // impossible
                continue;
            }
            // Replace the scope with the inner scope
            // TODO: Analyzing if_node->children['cond'] should affect $outer_scope?
            $inner_scope = new VariableTrackingBranchScope($outer_scope);
            $inner_scope = $this->analyze($inner_scope, $if_node);
            $inner_scope_list[] = $inner_scope;
            $cond_node = $if_node->children['cond'];
            if ($cond_node === null || (\is_scalar($cond_node) && $cond_node)) {
                $merge_parent_scope = false;
            }
        }

        // Merge inner scope into outer scope
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        return $outer_scope->mergeBranchScopeList($inner_scope_list, $merge_parent_scope, self::$variable_graph);
    }
}
