<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast;
use ast\Node;

/**
 * Analyzes uses of goto
 */
class GotoAnalyzer
{
    /**
     * Finds the label set for the scope of a node with parent node list $parent_node_list
     * @param list<Node> $parent_node_list
     * @return array<string,true>
     */
    public static function getLabelSet(array $parent_node_list) : array
    {
        // Find the AST_STMT_LIST that is the root of the function-like
        $prev_node = null;
        for ($i = \count($parent_node_list) - 1; $i >= 0; $i--) {
            $node = $parent_node_list[$i];
            if (\in_array($node->kind, [ast\AST_FUNC_DECL, ast\AST_CLOSURE, ast\AST_METHOD], true)) {
                break;
            }
            $prev_node = $node;
        }
        if (!$prev_node) {
            return [];
        }
        // $prev_node is the AST_STMT_LIST in the global scope or the nearest function-like scope.
        // @phan-suppress-next-line PhanUndeclaredProperty deliberately adding this to a dynamic property to avoid recomputing it for large function bodies.
        return $prev_node->used_label_set ?? ($prev_node->used_label_set = self::computeLabelSet($prev_node));
    }

    /**
     * @return array<string,true> the set of labels that are used by "goto label" in this function-like scope or global scope.
     */
    private static function computeLabelSet(Node $node) : array
    {
        $result = [];
        $nodes = [];
        while (true) {
            $kind = $node->kind;
            // fprintf(STDERR, "Processing node of kind %s\n", ast\get_kind_name($kind));
            switch ($kind) {
                case ast\AST_FUNC_DECL:
                case ast\AST_CLOSURE:
                case ast\AST_METHOD:
                case ast\AST_CLASS:
                    break;
                case ast\AST_GOTO:
                    $result[(string)$node->children['label']] = true;
                    break;
                default:
                    foreach ($node->children as $child_node) {
                        if ($child_node instanceof Node) {
                            $nodes[] = $child_node;
                        }
                    }
            }
            if (\count($nodes) === 0) {
                return $result;
            }
            $node = \array_pop($nodes);
        }
    }
}
