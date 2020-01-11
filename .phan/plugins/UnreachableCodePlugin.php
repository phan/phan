<?php

declare(strict_types=1);

use ast\Node;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This file checks for syntactically unreachable statements in
 * the global scope or function bodies.
 *
 * It hooks into one event:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
 *   file being analyzed
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV3
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class UnreachableCodePlugin extends PluginV3 implements PostAnalyzeNodeCapability
{

    /**
     * @return string - The name of the visitor that will be called (formerly analyzeNode)
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return UnreachableCodeVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
final class UnreachableCodeVisitor extends PluginAwarePostAnalysisVisitor
{
    // A plugin's visitors should NOT implement visit(), unless they need to.

    private const DECL_KIND_SET = [
        \ast\AST_CLASS      => true,
        \ast\AST_FUNC_DECL  => true,
        \ast\AST_CONST      => true,
    ];

    /**
     * @param Node $node
     * A node to analyze
     * @override
     */
    public function visitStmtList(Node $node): void
    {
        $child_nodes = $node->children;

        $last_node_index = count($child_nodes) - 1;
        foreach ($child_nodes as $i => $node) {
            if (!\is_int($i)) {
                throw new AssertionError("Expected integer index");
            }
            if ($i >= $last_node_index) {
                break;
            }
            if (!($node instanceof Node)) {
                continue;
            }
            if (!BlockExitStatusChecker::willUnconditionallySkipRemainingStatements($node)) {
                continue;
            }
            // Skip over empty statements and scalar statements.
            for ($j = $i + 1; array_key_exists($j, $child_nodes); $j++) {
                $next_node = $child_nodes[$j];
                if (!($next_node instanceof Node && $next_node->lineno > 0)) {
                    continue;
                }
                if (array_key_exists($next_node->kind, self::DECL_KIND_SET)) {
                    if ($this->context->isInGlobalScope()) {
                        continue;
                    }
                }
                $context = clone($this->context)->withLineNumberStart($next_node->lineno);
                if ($this->context->isInFunctionLikeScope()) {
                    if ($this->context->getFunctionLikeInScope($this->code_base)->checkHasSuppressIssueAndIncrementCount('PhanPluginUnreachableCode')) {
                        // don't emit the below issue.
                        break;
                    }
                }
                $this->emitPluginIssue(
                    $this->code_base,
                    $context,
                    'PhanPluginUnreachableCode',
                    'Unreachable statement detected',
                    []
                );
                break;
            }
            break;
        }
    }
}
// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new UnreachableCodePlugin();
