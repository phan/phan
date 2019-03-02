<?php declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use Phan\PluginV2\AnalyzePropertyCapability;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;

/**
 * This file demonstrates plugins for Phan.
 * This Plugin hooks into five events;
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
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
 * - analyzeProperty
 *   Once all functions have been parsed, this method will
 *   be called on every property in the code base.
 *
 * A plugin file must
 *
 * - Contain a class that inherits from \Phan\PluginV2
 *   and implements one or more `Capability`s.
 *
 * - End by returning an instance of that class.
 *
 * It is assumed without being checked that plugins aren't
 * mangling state within the passed code base or context.
 *
 * Note: When adding new plugins,
 * add them to the corresponding section of README.md
 */
class DemoPlugin extends PluginV2 implements
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    PostAnalyzeNodeCapability,
    AnalyzePropertyCapability
{

    /**
     * @return string - The name of the visitor that will be called (formerly analyzeNode)
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName() : string
    {
        return DemoNodeVisitor::class;
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
            self::emitIssue(
                $code_base,
                $class->getContext(),
                'DemoPluginClassName',
                "Class {CLASS} cannot be called `Class`",
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
            self::emitIssue(
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
            self::emitIssue(
                $code_base,
                $function->getContext(),
                'DemoPluginFunctionName',
                "Function {FUNCTION} cannot be called `function`",
                [(string)$function->getFQSEN()]
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     *
     * @return void
     *
     * @override
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    ) {
        // As an example, we test to see if the name of the
        // property is `property`, and emit an issue if it is.
        if ($property->getName() == 'property') {
            self::emitIssue(
                $code_base,
                $property->getContext(),
                'DemoPluginPropertyName',
                "Property {PROPERTY} should not be called `property`",
                [(string)$property->getFQSEN()]
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
class DemoNodeVisitor extends PluginAwarePostAnalysisVisitor
{
    // Subclasses should declare protected $parent_node_list as an instance property if they need to know the list.

    // @var array<int,Node> - Set after the constructor is called if an instance property with this name is declared
    // protected $parent_node_list;

    // A plugin's visitors should NOT implement visit(), unless they need to.

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
        if (!$class_name) {
            return;
        }

        // As an example, enforce that we cannot call
        // instanceof against 'object'.
        if ($class_name == 'object') {
            $this->emit(
                'PhanPluginInstanceOfObject',
                "Cannot call instanceof against `object`"
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new DemoPlugin();
