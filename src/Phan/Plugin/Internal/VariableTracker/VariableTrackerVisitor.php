<?php declare(strict_types=1);
namespace Phan\Plugin\Internal\VariableTracker;

use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use ast\Node;

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

    /** @var array<int,Node> */
    private $parent_node_list = [];

    public function __construct(VariableTrackingScope $scope)
    {
        $this->scope = $scope;
    }

    /**
     * This is the default implementation for node types which don't have any overrides
     * @return void
     * @override
     */
    public function visit(Node $node)
    {
        foreach ($node->children as $child_node) {
            if (!($child_node instanceof Node)) {
                continue;
            }

            $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        }
    }

    /**
     * @override
     */
    public function visitAssignRef(Node $node)
    {
        $this->analyzeAssign($node, true);
    }


    /**
     * @override
     */
    public function visitAssign(Node $node)
    {
        $this->analyzeAssign($node, false);
    }

    private function analyzeAssign(Node $node, bool $is_ref)
    {
        $expr = $node->children['expr'];
        if ($expr instanceof Node) {
            $this->analyze($this->scope, $node, $expr);
        }
        $this->analyzeAssignmentTarget($node->children['var'], $is_ref);
    }

    private function analyzeAssignmentTarget($node, bool $is_ref)
    {
        if (!($node instanceof Node)) {
            return;
        }
        $kind = $node->kind;
        if ($kind === \ast\AST_VAR) {
            $name = $node->children['name'];
            if (!is_string($name)) {
                return;
            }
            self::$variable_graph->recordVariableDefinition($name, $node, $this->scope);
            $this->scope->recordDefinition($name, $node);
        }
        // TODO: Analyze properties, array access, and function calls.
    }

    public function handleMissingNodeKind(Node $node)
    {
        // do nothing
    }

    /**
     * This is an abstraction for getting a new, updated context for a child node.
     *
     * @param Node $child_node - The node which will be analyzed to create the updated context.
     */
    private function analyze(VariableTrackingScope $scope, Node $node, Node $child_node)
    {
        // Modify the original object instead of creating a new BlockAnalysisVisitor.
        // this is slightly more efficient, especially if a large number of unchanged parameters would exist.
        $old_scope = $this->scope;
        $this->scope = $scope;
        $this->parent_node_list[] = $node;
        try {
            return $this->{Element::VISIT_LOOKUP_TABLE[$child_node->kind] ?? 'handleMissingNodeKind'}($child_node);
        } finally {
            $this->scope = $scope;
            \array_pop($this->parent_node_list);
        }
    }

    /**
     * Do not recurse into function declarations within a scope
     * @override
     */
    public function visitFuncDecl(Node $unused_node)
    {
        return;
    }

    /**
     * Do not recurse into class declarations within a scope
     * @override
     */
    public function visitClass(Node $unused_node)
    {
        return;
    }

    /**
     * Do not recurse into closure declarations within a scope.
     *
     * FIXME: Check closure use variables without checking statements
     * @override
     */
    public function visitClosure(Node $unused_node)
    {
        return;
    }

    /**
     * @override
     * Common no-op
     */
    public function visitName(Node $unused_node)
    {
        return;
    }

    /**
     * TODO: Check if the current context is a function call passing an argument by reference
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
    }
}
