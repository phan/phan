<?php declare(strict_types=1);

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Plugin;
use Phan\Plugin\PluginImplementation;
use ast\Node;

/**
 * @deprecated - Compare this with DemoPlugin.php to see how to upgrade a Plugin to PluginV2.
 *
 * This file demonstrates the old version of plugins for Phan. Plugins hook into
 * four events;
 *
 * - analyzeNode
 *   This method is called on every AST node from every
 *   file being analyzed
 *
 * - analyzeClass
 *   Once all classes have been parsed, this method will be
 *   called on every class that is found in the code base
 *
 * - analyzeMethod
 *   Once all methods are parsed, this method will be called
 *   on every method in the code base
 *
 * - analyzeFunction
 *   Once all functions have been parsed, this method will
 *   be called on every function in the code base.
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
class DemoLegacyPlugin extends PluginImplementation {

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node
     * The php-ast Node being analyzed.
     *
     * @param Node $node
     * The parent node of the given node (if one exists).
     *
     * @return void
     *
     * @override
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $unused_parent_node = null
    ) {
        // Invoke the `DemoLegacyNodeVisitor` (defined later in
        // this file) on the given node, allowing it to run
        // a method based on the kind of the given node.
        (new DemoLegacyNodeVisitor($code_base, $context, $this))(
            $node
        );
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        // As an example, we test to see if the name of
        // the class is `Class`, and emit an issue explain that
        // the name is not allowed.
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        if ($class->getName() == 'Class') {
            $this->emitIssue(
                $code_base,
                $class->getContext(),
                'DemoPluginClassName',
                "Class {CLASS} cannot be called `Class`"
                [(string)$class->getFQSEN()]
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        // As an example, we test to see if the name of the
        // method is `function`, and emit an issue if it is.
        // NOTE: Placeholders can be found in \Phan\Issue::uncolored_format_string_for_replace
        if ($method->getName() == 'function') {
            $this->emitIssue(
                $code_base,
                $method->getContext(),
                'DemoPluginMethodName',
                "Method {METHOD} cannot be called `function`",
                [(string)$method->getFQSEN()]
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        // As an example, we test to see if the name of the
        // function is `function`, and emit an issue if it is.
        if ($function->getName() == 'function') {
            $this->emitIssue(
                $code_base,
                $function->getContext(),
                'DemoPluginFunctionName',
                "Function {FUNCTION} cannot be called `function`",
                [(string)$function->getFQSEN()]
            );
        }
    }

}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class DemoLegacyNodeVisitor extends AnalysisVisitor {

    /** @var Plugin */
    private $plugin;

    public function __construct(
        CodeBase $code_base,
        Context $context,
        Plugin $plugin
    ) {
        // After constructing on parent, `$code_base` and
        // `$context` will be available as protected properties
        // `$this->code_base` and `$this->context`.
        parent::__construct($code_base, $context);

        // We take the plugin so that we can call
        // `$this->plugin->emitIssue(...)` on it to emit issues
        // to the user.
        $this->plugin = $plugin;
    }

    /**
     * Default visitor that does nothing
     *
     * @param Node $unused_node
     * A node to analyze
     *
     * @return void
     *
     * @override
     */
    public function visit(Node $unused_node)
    {
        // This method will be called on all nodes for which
        // there is no implementation of it's kind visitor.
        //
        // To see what kinds of nodes are passing through here,
        // you can run `Debug::printNode($unused_node)`.

        // Debug::printNode($unused_node);
    }

    /**
     * @param Node $node
     * A node to analyze
     *
     * @return void
     *
     * @override
     */
    public function visitInstanceof(Node $node)
    {
        // Debug::printNode($node);

        $class_name = $node->children['class']->children['name'] ?? null;

        // If we can't figure out the name of the class,  don't
        // bother continuing.
        if (empty($class_name)) {
            return;
        }

        // As an example, enforce that we cannot call
        // instanceof against 'object'.
        if ($class_name == 'object') {
            $this->plugin->emitIssue(
                $this->code_base,
                $this->context,
                'PhanPluginInstanceOfObject',
                "Cannot call instanceof against `object`"
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which its defined.
/** @suppress PhanDeprecatedClass - Only using a closure so that I can suppress Phan issue types */
return (function() : DemoLegacyPlugin { return new DemoLegacyPlugin(); })();
