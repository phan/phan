<?php

declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use Phan\AST\AnalysisVisitor;
use Phan\AST\Visitor\Element;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Plugin\ConfigPluginSet;

/**
 * CombinedAnalysisVisitor combines PreOrderAnalysisVisitor and PostOrderAnalysisVisitor
 * functionality into a single visitor to reduce object instantiation overhead during analysis.
 *
 * This visitor performs both pre-order and post-order analysis in a single call,
 * reducing the number of visitor objects created during AST traversal.
 *
 * Note: This class doesn't use the standard visit() method pattern, but instead
 * provides analyzeBoth() for combined analysis.
 */
class CombinedAnalysisVisitor extends AnalysisVisitor
{
    /**
     * @var list<Node> a list of parent nodes of the currently analyzed node,
     * within the current global or function-like scope
     */
    private $parent_node_list;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param list<Node> $parent_node_list
     * The parent node list of the node being analyzed
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        array $parent_node_list
    ) {
        parent::__construct($code_base, $context);
        $this->parent_node_list = $parent_node_list;
    }

    /**
     * Performs combined pre-order and post-order analysis on a node.
     *
     * This method:
     * 1. Runs pre-order analysis using PreOrderAnalysisVisitor
     * 2. Runs pre-order plugins
     * 3. Runs post-order analysis using PostOrderAnalysisVisitor
     * 4. Runs post-order plugins
     *
     * @param Node $node The AST node to analyze
     * @return Context The updated context after both analysis phases
     */
    public function analyzeBoth(Node $node): Context
    {
        $context = $this->context;

        // Pre-order analysis phase
        $context = (new PreOrderAnalysisVisitor(
            $this->code_base,
            $context
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

        // Pre-order plugin phase
        ConfigPluginSet::instance()->preAnalyzeNode(
            $this->code_base,
            $context,
            $node
        );

        // Post-order analysis phase
        $context = (new PostOrderAnalysisVisitor(
            $this->code_base,
            $context->withLineNumberStart($node->lineno),
            $this->parent_node_list
        ))->{Element::VISIT_LOOKUP_TABLE[$node->kind] ?? 'handleMissingNodeKind'}($node);

        // Post-order plugin phase
        ConfigPluginSet::instance()->postAnalyzeNode(
            $this->code_base,
            $context,
            $node,
            $this->parent_node_list
        );

        return $context;
    }

    /**
     * Implementation of abstract visit method - not used in this visitor.
     * Use analyzeBoth() instead.
     * @suppress PhanUnusedPublicMethodParameter
     * @return never
     */
    public function visit(Node $node): Context
    {
        throw new \RuntimeException('CombinedAnalysisVisitor::visit() should not be called directly. Use analyzeBoth() instead.');
    }
}