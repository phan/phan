<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Context;

use ast\Node;

/**
 * @deprecated - New plugins should use PostAnalyzeNodeCapability
 */
interface LegacyPostAnalyzeNodeCapability
{
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
     * @param array<int,Node> $parent_node_list
     * The parent node of the given node (if any exist).
     *
     * @return void
     *
     * @deprecated
     */
    public function postAnalyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        array $parent_node_list = []
    );
}
