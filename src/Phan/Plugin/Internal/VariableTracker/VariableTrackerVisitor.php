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

    /**
     * @return VariableTrackingScope
     */
    private function analyzeAssignmentTarget($node, bool $is_ref)
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
                self::$variable_graph->recordVariableDefinition($name, $node, $this->scope);
                $this->scope->recordDefinition($name, $node);
                break;
            case ast\AST_REF:
                $this->scope = $this->analyzeAssignmentTarget($node->children['var'], true);
                break;
            // TODO: Analyze properties, array access, and function calls.
        }
        return $this->scope;
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
        }
        return $this->scope;
    }

    /**
     * @return VariableTrackingScope
     */
    public function visitForeach(Node $node) {
        // foreach ($expr as $key => $value) { stmts }
        $expr_node = $node->children['expr'];
        $this->analyzeWhenValidNode($this->scope, $expr_node);

        $key_node = $node->children['key'];
        $this->analyzeAssignmentTarget($key_node, false);

        $value_node = $node->children['value'];
        $this->analyzeAssignmentTarget($value_node, false);  // analyzeAssignmentTarget checks for AST_REF

        // TODO: Update graph: inner loop definitions can be used inside the loop.
        // TODO: Create a branchScope? - Loop iterations can run 0 times.
        $inner_scope = $this->visitStmtList($node->children['stmts']);
        return $this->scope;
    }
}
