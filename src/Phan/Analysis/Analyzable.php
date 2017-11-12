<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use ast\Node;

/**
 * Objects implementing this trait store a handle to
 * the AST node that defines them and allows us to
 * reanalyze them later on
 */
trait Analyzable
{

    /**
     * @var Node
     * The AST Node defining this object. We keep a
     * reference to this so that we can come to it
     * and
     */
    private $node = null;

    /**
     * @var int
     * The depth of recursion on this analyzable
     * object
     */
    private static $recursion_depth = 0;

    /**
     * @param Node $node
     * The AST Node defining this object. We keep a
     * reference to this so that we can come to it
     * and
     */
    public function setNode(Node $node)
    {
        // Don't waste the memory if we're in quick mode
        if (Config::get_quick_mode()) {
            return;
        }

        $this->node = $node;
    }

    /**
     * @return bool
     * True if we have a node defined on this object
     */
    public function hasNode() : bool
    {
        return !empty($this->node);
    }

    /**
     * @return Node
     * The AST node associated with this object
     */
    public function getNode() : Node
    {
        return $this->node;
    }

    /**
     * @return Context
     * Analyze the node associated with this object
     * in the given context
     */
    public function analyze(Context $context, CodeBase $code_base) : Context
    {
        // Don't do anything if we care about being
        // fast
        if (Config::get_quick_mode()) {
            return $context;
        }

        if (!$this->hasNode()) {
            return $context;
        }

        // Closures depend on the context surrounding them such
        // as for getting `use(...)` variables. Since we don't
        // have them, we can't re-analyze them until we change
        // that.
        //
        // TODO: Store the parent context on Analyzable objects
        $definition_node = $this->getNode();
        if ($definition_node->kind === \ast\AST_CLOSURE) {
            // TODO: Pick up 'uses' when this is a closure invoked inline (e.g. array_map(function($x) use($localVar) {...}, args
            // TODO: Investigate replacing the types of these with 'mixed' for quick mode re-analysis, or checking if the type will never vary.
            if (!empty($definition_node->children['uses'])) {
                return $context;
            }
        }
        // Don't go deeper than one level in
        // TODO: Due to optimizations in checking for duplicate parameter lists, it should now be possible to increase this depth limit.
        if (self::$recursion_depth >= 2) {
            return $context;
        }

        self::$recursion_depth++;

        try {
            // Analyze the node in a cloned context so that we
            // don't overwrite anything
            return (new BlockAnalysisVisitor($code_base, clone($context)))(
                $definition_node
            );
        } finally {
            self::$recursion_depth--;
        }
    }

    /**
     * Gets the recursion depth. Starts at 0, increases the deeper the recursion goes
     */
    public function getRecursionDepth() : int
    {
        return self::$recursion_depth;
    }
}
