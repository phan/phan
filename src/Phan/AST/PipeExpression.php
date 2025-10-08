<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;

use function array_unshift;
use function array_values;
use function in_array;

/**
 * Helper utilities for analyzing the PHP pipe operator expressions.
 */
final class PipeExpression
{
    /**
     * Creates a synthetic call node representing the pipe expression.
     *
     * In PHP's AST, the pipe operator is represented as a binary operation where the right-hand side
     * is a call expression using AST_CALLABLE_CONVERT for its arguments. Phan analyses calls that use
     * AST_ARG_LIST, so this helper clones the call node and replaces the argument list with one that
     * explicitly includes the piped expression as the first positional argument.
     *
     * @return Node|null the cloned call node with normalized arguments, or null if the structure is unexpected
     */
    public static function createSyntheticCall(Node $pipe_node): ?Node
    {
        $right_node = $pipe_node->children['right'] ?? null;
        if (!($right_node instanceof Node)) {
            return null;
        }
        if (!in_array($right_node->kind, [ast\AST_CALL, ast\AST_METHOD_CALL, ast\AST_STATIC_CALL, ast\AST_NULLSAFE_METHOD_CALL], true)) {
            return null;
        }

        $args_node = $right_node->children['args'] ?? null;
        if (!($args_node instanceof Node) || $args_node->kind !== ast\AST_CALLABLE_CONVERT) {
            return null;
        }

        $call_clone = clone $right_node;
        $call_clone->children = $right_node->children;

        $arg_children = array_values($args_node->children);
        array_unshift($arg_children, $pipe_node->children['left']);
        $call_clone->children['args'] = new Node(
            ast\AST_ARG_LIST,
            0,
            $arg_children,
            $args_node->lineno ?? $pipe_node->lineno
        );
        return $call_clone;
    }

    private function __construct()
    {
    }
}
