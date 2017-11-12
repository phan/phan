<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Plugin;
use ast\Node;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extends this class and return an instance
 * of themselves.
 *
 * @suppress PhanUnreferencedClass
 */
class PluginImplementation extends Plugin
{
    /**
     * Do a first-pass analysis of a node before Phan
     * does its full analysis. This hook allows you to
     * update types in the CodeBase before analysis
     * happens.
     *
     * @param CodeBase $code_base (@phan-unused-param)
     * The code base in which the node exists
     *
     * @param Context $context (@phan-unused-param)
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node (@phan-unused-param)
     * The php-ast Node being analyzed.
     *
     * @return void
     */
    public function preAnalyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node
    ) {
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param)
     * The code base in which the node exists
     *
     * @param Context $context (@phan-unused-param)
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node (@phan-unused-param)
     * The php-ast Node being analyzed.
     *
     * @param ?Node $parent_node (@phan-unused-param)
     * The parent node of the given node (if one exists).
     *
     * @return void
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param)
     * The code base in which the class exists
     *
     * @param Clazz $class (@phan-unused-param)
     * A class being analyzed
     *
     * @return void
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param)
     * The code base in which the method exists
     *
     * @param Method $method (@phan-unused-param)
     * A method being analyzed
     *
     * @return void
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
    }

    /**
     * @param CodeBase $code_base (@phan-unused-param)
     * The code base in which the function exists
     *
     * @param Func $function (@phan-unused-param)
     * A function being analyzed
     *
     * @return void
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
    }

    // Internal methods, for use by ConfigPluginSet

    /**
     * @return bool true if $method_name is defined by the subclass of PluginAwareAnalysisVisitor, and not by PluginAwareAnalysisVisitor or one of it's parents.
     */
    final public static function isDefinedInSubclass(string $method_name) : bool
    {
        $method = new \ReflectionMethod(static::class, $method_name);
        return is_subclass_of($method->getDeclaringClass()->name, self::class);
    }
    // End of internal methods.
}
