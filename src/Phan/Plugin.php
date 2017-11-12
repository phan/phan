<?php declare(strict_types=1);
namespace Phan;

use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Func;
use Phan\PluginV2\LegacyPreAnalyzeNodeCapability;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use Phan\PluginV2\LegacyAnalyzeNodeCapability;
use ast\Node;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extends this class and return an instance
 * of themselves.
 *
 * @deprecated - Use PluginV2 instead
 */
abstract class Plugin extends PluginV2 implements
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    AnalyzeMethodCapability,
    LegacyAnalyzeNodeCapability,
    LegacyPreAnalyzeNodeCapability
{

    /**
     * Do a first-pass analysis of a node before Phan
     * does its full analysis. This hook allows you to
     * update types in the CodeBase before analysis
     * happens.
     *
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
     * The parent node of the given node (if one exists).
     *
     * @return void
     *
     * @deprecated
     */
    abstract public function preAnalyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node
    );

    /**
     * Analyze the given node in the given context after
     * Phan has analyzed the node.
     *
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
     * @deprecated
     */
    abstract public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    );

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     */
    abstract public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    );

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    abstract public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    );

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     */
    abstract public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    );
}
