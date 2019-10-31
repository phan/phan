<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use Phan\AST\PhanAnnotationAdder;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;

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
     * Keep a reference to the Node which declared this analyzable object so that we can use it later.
     *
     * @param Node $node
     * The AST Node defining this object.
     */
    public function setNode(Node $node) : void
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
        return $this->node !== null;
    }

    /**
     * @return Node
     * The AST node associated with this object
     * NOTE: This is non-null if hasNode is true
     * @suppress PhanTypeMismatchDeclaredReturnNullable
     */
    public function getNode() : ?Node
    {
        return $this->node;
    }

    /**
     * Clears the node so that it won't be used for analysis.
     * @suppress PhanTypeMismatchProperty
     */
    protected function clearNode() : void
    {
        $this->node = null;
    }

    /**
     * Ensure that annotations about what flags a function declaration has have been added
     * @suppress PhanUndeclaredProperty deliberately using dynamic properties
     */
    public static function ensureDidAnnotate(Node $node) : void
    {
        if (!isset($node->did_annotate_node)) {
            // Set this to true to indicate that this node has already
            // been annotated with any extra information
            // from the class.
            // (Nodes for a FunctionInterface can be both from the parse phase and the analysis phase)
            $node->did_annotate_node = true;
            PhanAnnotationAdder::applyToScope($node);
        }
    }

    /**
     * @return Context
     * Analyze the node associated with this object
     * in the given context
     * @suppress PhanUnreferencedPublicMethod phan has issues with dead code detection with traits and interfaces
     */
    public function analyze(Context $context, CodeBase $code_base) : Context
    {
        // Don't do anything if we care about being
        // fast
        if (Config::get_quick_mode()) {
            return $context;
        }

        $definition_node = $this->node;
        if (!$definition_node) {
            return $context;
        }
        self::ensureDidAnnotate($definition_node);

        // Closures depend on the context surrounding them such
        // as for getting `use(...)` variables. Since we don't
        // have them, we can't re-analyze them until we change
        // that.
        //
        // TODO: Store the parent context on Analyzable objects
        if ($definition_node->kind === \ast\AST_CLOSURE) {
            // TODO: Pick up 'uses' when this is a closure invoked inline (e.g. array_map(function($x) use($localVar) {...}, args
            // TODO: Investigate replacing the types of these with 'mixed' for quick mode re-analysis, or checking if the type will never vary.
            if (isset($definition_node->children['uses'])) {
                return $context;
            }
        }
        // Stop upon reaching the maximum depth
        if (self::$recursion_depth >= self::getMaxRecursionDepth()) {
            return $context;
        }

        self::$recursion_depth++;

        try {
            // Analyze the node in a cloned context so that we
            // don't overwrite anything
            return (new BlockAnalysisVisitor($code_base, clone($context)))->__invoke(
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

    /**
     * Gets the maximum recursion depth.
     */
    public static function getMaxRecursionDepth() : int
    {
        return Config::getValue('maximum_recursion_depth');
    }
}
