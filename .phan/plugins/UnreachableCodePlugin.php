<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Analysis\BlockExitStatusChecker;
use Phan\Language\Context;
use Phan\Language\UnionType;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use ast\Node;

/**
 * This file checks for syntactically unreachable statements in
 * the global scope or function bodies.
 *
 * It hooks into one event:
 *
 * - getAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
 *   file being analyzed
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\Plugin
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
final class UnreachableCodePlugin extends PluginV2
    implements AnalyzeNodeCapability {

    /**
     * @return string - The name of the visitor that will be called (formerly analyzeNode)
     */
    public static function getAnalyzeNodeVisitorClassName() : string
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
final class UnreachableCodeVisitor extends PluginAwareAnalysisVisitor {
    // A plugin's visitors should NOT implement visit(), unless they need to.

    const DECL_KIND_SET = [
        \ast\AST_CLASS      => true,
        \ast\AST_FUNC_DECL  => true,
        \ast\AST_CONST      => true,
    ];

    /**
     * @param Node $node
     * A node to analyze
     *
     * @return void
     *
     * @override
     */
    public function visitStmtList(Node $node)
    {
        $child_nodes = $node->children;
        // Debug::printNode($node);

        $last_node_index = count($child_nodes) - 1;
        foreach ($child_nodes as $i => $node) {
            if ($i >= $last_node_index) {
                break;
            }
            if (!($node instanceof Node)) {
                continue;
            }
            if (!BlockExitStatusChecker::willUnconditionallyThrowOrReturn($node)) {
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
                    if ($this->context->getFunctionLikeInScope($this->code_base)->hasSuppressIssue('PhanPluginUnreachableCode')) {
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
// end of the file in which its defined.
return new UnreachableCodePlugin;
