<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Closure;
use Phan\Language\Context;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This plugin checks for the definition of a region selected by a user.
 */
class NodeSelectionPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @param ?Closure(Context,Node,list<Node>):void $closure
     * @return void
     * TODO: Fix false positive TypeMismatchDeclaredParam with Closure $closure = null in this method
     */
    public function setNodeSelectorClosure(?Closure $closure): void
    {
        NodeSelectionVisitor::$closure = $closure;
    }

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return NodeSelectionVisitor::class;
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class NodeSelectionVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * A closure that this visitor dispatches to when the desired node (marked with isSelected) is found
     *
     * @var ?Closure(Context,Node,Node[]):void $closure
     */
    public static $closure = null;

    // A plugin's visitors should not override visit() unless they need to.

    /**
     * This is the catch-all for Nodes with kinds that don't have specialized methods
     * @param Node $node
     * A node to check
     *
     * @param list<Node> $parent_node_list
     * @see ConfigPluginSet::prepareNodeSelectionPlugin() for how this is called
     */
    public function visitCommonImplementation(Node $node, array $parent_node_list): void
    {
        if (!\property_exists($node, 'isSelected')) {
            return;
        }
        $closure = NodeSelectionVisitor::$closure;
        if (!$closure) {
            // This should not be possible.
            // fwrite(STDERR, "Calling NodeSelectionVisitor without a closure\n");
            return;
        }
        $closure($this->context, $node, $parent_node_list);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new NodeSelectionPlugin();
