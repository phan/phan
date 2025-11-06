<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;

/**
 * Normalizes AST nodes to a consistent representation across different AST versions and parsers.
 *
 * This class handles differences in how various AST versions represent certain language constructs.
 * For example, AST version 120 represents `clone` as an AST_CALL node instead of AST_CLONE,
 * which requires normalization for consistency.
 */
class ASTNormalizer
{
    /**
     * Normalize an AST tree by converting AST_CALL nodes representing 'clone' to AST_CLONE nodes.
     *
     * AST versions 110+ (including 120) from php-ast represent `clone $expr` as:
     * ```
     * AST_CALL(
     *     expr: AST_NAME('clone'),
     *     args: AST_ARG_LIST([expr])
     * )
     * ```
     *
     * This method normalizes such nodes to the standard AST_CLONE format:
     * ```
     * AST_CLONE(
     *     expr: expr
     * )
     * ```
     *
     * This ensures downstream visitors don't need to special-case clone handling.
     *
     * @param Node $node The root AST node to normalize
     * @return Node The normalized AST
     */
    public static function normalizeCloneNodes(Node $node): Node
    {
        // Process children recursively to find and normalize clone nodes
        self::normalizeCloneNodesRecursive($node);
        return $node;
    }

    /**
     * Recursively traverse the AST and normalize clone nodes.
     *
     * @param Node $node The current node being processed
     */
    private static function normalizeCloneNodesRecursive(Node $node): void
    {
        foreach ($node->children as $key => $child) {
            if ($child instanceof Node) {
                // Check if this is an AST_CALL node representing 'clone'
                if ($child->kind === \ast\AST_CALL && self::isCloneCall($child)) {
                    // Replace with normalized AST_CLONE node
                    $node->children[$key] = self::convertCallToClone($child);
                } else {
                    // Recursively process child nodes
                    self::normalizeCloneNodesRecursive($child);
                }
            }
        }
    }

    /**
     * Check if an AST_CALL node represents a clone operation.
     *
     * @param Node $node An AST_CALL node
     * @return bool True if the call is to 'clone'
     */
    private static function isCloneCall(Node $node): bool
    {
        $expr = $node->children['expr'] ?? null;
        if (!($expr instanceof Node) || $expr->kind !== \ast\AST_NAME) {
            return false;
        }
        return ($expr->children['name'] ?? null) === 'clone';
    }

    /**
     * Convert an AST_CALL node representing 'clone' to an AST_CLONE node.
     *
     * @param Node $call_node The AST_CALL node
     * @return Node The normalized AST_CLONE node
     */
    private static function convertCallToClone(Node $call_node): Node
    {
        $args = $call_node->children['args'] ?? null;
        $expr = null;

        if ($args instanceof Node && isset($args->children[0])) {
            $expr = $args->children[0];
        }

        // Create a new AST_CLONE node with the proper structure
        return new Node(
            \ast\AST_CLONE,
            0,
            ['expr' => $expr],
            $call_node->lineno
        );
    }
}
