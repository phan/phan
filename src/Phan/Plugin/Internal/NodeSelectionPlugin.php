<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\Language\Context;
use Phan\PluginV2;
use Phan\PluginV2\PostAnalyzeNodeCapability;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use ast\Node;

/**
 * This plugin checks for the definition of a region selected by a user.
 */
class NodeSelectionPlugin extends PluginV2 implements PostAnalyzeNodeCapability
{
    /**
     * @param ?Closure(Context,Node):void $closure
     * TODO: Fix false positive TypeMismatchDeclaredParam with Closure $closure = null in this method
     */
    public function setNodeSelectorClosure($closure)
    {
        NodeSelectionVisitor::$closure = $closure;
    }

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return NodeSelectionVisitor::class;
    }
}

trait NodeSelectionTrait
{
    /**
     * @var Context $context (part of PluginAwarePostAnalysisVisitor)
     */
    protected $context;

    /**
     * @param Node $node
     * A node to check
     *
     * @return void
     */
    public function visitCommonImplementation(Node $node)
    {
        if (!\property_exists($node, 'isSelected')) {
            return;
        }
        if (!NodeSelectionVisitor::$closure) {
            fwrite(STDERR, "Calling NodeSelectionVisitor without a closure\n");
            return;
            // TODO: Throw
        }
        (NodeSelectionVisitor::$closure)($this->context, $node);
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
    public static $closure = null;
    public static $desired_node = null;

    const STATUS_NODE_SELECTED = 1;

    // A plugin's visitors should not override visit() unless they need to.

    use NodeSelectionTrait {
        visitCommonImplementation as visitClass;
        visitCommonImplementation as visitCall;
        visitCommonImplementation as visitStaticCall;
        visitCommonImplementation as visitMethodCall;
        visitCommonImplementation as visitName;
        visitCommonImplementation as visitProp;
        visitCommonImplementation as visitStaticProp;
        visitCommonImplementation as visitClassConst;
        // TODO: VisitNew
        // TODO: implement, extend, use trait, etc.
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new NodeSelectionPlugin;
