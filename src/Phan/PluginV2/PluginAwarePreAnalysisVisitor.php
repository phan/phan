<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use Phan\CodeBase;
use Phan\Language\Context;
use ast\Node;

/**
 * For plugins which define their own pre-order analysis behaviors in the analysis phase.
 * Called on a node before PluginAwareAnalysisVisitor implementations.
 */
abstract class PluginAwarePreAnalysisVisitor extends AnalysisVisitor {
    // Implementations should omit the constructor or call parent::__construct(CodeBase $code_base, Context $context)

    /**
     * This is an empty visit() body.
     * Don't override this unless you need to, analysis is more efficient if Phan knows it doesn't need to call a method.
     * @see self::isDefinedInSubclass
     *
     * @return void
     */
    public function visit(Node $node)
    {
    }

    /**
     * @return int[] The list of $node->kind values this plugin is capable of analyzing.
     */
    public static final function getHandledNodeKinds() : array
    {
        $defines_visit = self::isDefinedInSubclass('visit');
        $kinds = [];
        foreach (Element::VISIT_LOOKUP_TABLE as $kind => $method_name) {
            if ($defines_visit || self::isDefinedInSubclass($method_name)) {
                $kinds[] = $kind;
            }
        }
        return $kinds;
    }

    /**
     * This is a utility function used by ConfigPluginSet
     */
    public static final function staticInvoke(CodeBase $code_base, Context $context, Node $node)
    {
        // For backwards compatibility reasons, PreAnalysisVisitor doesn't support parent_node
        return (new static($code_base, $context))($node);
    }

    /**
     * @return bool true if $method_name is defined by the subclass of PluginAwarePreAnalysisVisitor, and not by PluginAwarePreAnalysisVisitor or one of it's parents.
     */
    private static final function isDefinedInSubclass(string $method_name) : bool
    {
        $method = new \ReflectionMethod(static::class, $method_name);
        return is_subclass_of($method->getDeclaringClass()->getName(), self::class);
    }
}
