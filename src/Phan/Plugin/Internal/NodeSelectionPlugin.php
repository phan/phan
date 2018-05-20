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
     * @var Context $context
     * @suppress PhanReadOnlyProtectedProperty (shared with PluginAwarePostAnalysisVisitor)
     */
    protected $context;

    /**
     * @param Node $node
     * A node to check
     *
     * @return void
     * @suppress PhanUnreferencedPublicMethod (TODO: Fix false positive. The aliases are used)
     */
    public function visitCommonImplementation(Node $node)
    {
        if (!\property_exists($node, 'isSelected')) {
            return;
        }
        if (!NodeSelectionVisitor::$closure) {
            // This should not be possible.
            // fwrite(STDERR, "Calling NodeSelectionVisitor without a closure\n");
            return;
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
        visitCommonImplementation as visitConst;
        visitCommonImplementation as visitUse;  // Uses visitUse instead of visitUseElem to be sure if it's a class/func/constant

        visitCommonImplementation as visitVar;  // For "go to type definition"
        // TODO: VisitNew
        // TODO: implement, extend, use trait, etc.
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
return new NodeSelectionPlugin;
