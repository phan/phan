<?php declare(strict_types=1);

use Phan\Analysis\PostOrderAnalyzer;
use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Plugin;
use Phan\PluginIssue;
use Phan\Plugin\PluginImplementation;
use ast\Node;

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class DollarDollarPlugin extends AnalysisVisitor implements PostOrderAnalyzer {
    use PluginIssue;

    /**
     * Default visitor that does nothing
     *
     * @param Node $node
     * A node to analyze
     *
     * @return void
     */
    public function visit(Node $node) {
    }

    /**
     * @param Node $node
     * A node to analyze
     *
     * @return void
     */
    public function visitVar(Node $node) {
        if ($node->children['name'] instanceof Node) {
            $this->emitPluginIssue(
                $this->code_base,
                $this->context,
                'PhanPluginDollarDollar',
                "$$ Variables are not allowed."
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return DollarDollarPlugin::class;
