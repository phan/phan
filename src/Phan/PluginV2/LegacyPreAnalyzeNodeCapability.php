<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;

use ast\Node;

/**
 * @deprecated - New plugins should use PreAnalyzeNodeCapability
 */
interface LegacyPreAnalyzeNodeCapability {
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
    public function preAnalyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node
    );
}
