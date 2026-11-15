<?php

declare(strict_types=1);

namespace Phan\AST;

use ast;
use ast\Node;
use WeakMap;

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
        $cache = self::$synthetic_calls ??= new WeakMap();
        if (isset($cache[$pipe_node])) {
            return $cache[$pipe_node];
        }
        $call_node = self::createSyntheticCallUncached($pipe_node);
        if ($call_node !== null) {
            $cache[$pipe_node] = $call_node;
        }
        return $call_node;
    }

    /**
     * The synthetic call of each pipe expression, kept for as long as the node of the pipe expression exists.
     *
     * Phan caches the types of nodes by their object id (Context::setCachedUnionTypeOfNode()).
     * If a new synthetic node was created every time, it could get the id of an earlier one that was freed,
     * and with it the cached type of another call, e.g. of an earlier stage of a chain of pipes.
     *
     * @var ?WeakMap<Node,Node>
     */
    private static $synthetic_calls = null;

    private static function createSyntheticCallUncached(Node $pipe_node): ?Node
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

        if (PartialApplication::isPartialApplication($args_node)) {
            // `$x |> f(1, ?)` and `$x |> f(1, ...)` pass $x in the place of the placeholder.
            // For other forms, such as `$x |> f(?, ?)`, the closure created by the partial application is called with $x.
            $placeholder_index = PartialApplication::getIndexOfSinglePositionalPlaceholder($args_node);
            if ($placeholder_index === null) {
                // Call the closure: `(f(?, ?))($x)`
                return new Node(
                    ast\AST_CALL,
                    0,
                    [
                        'expr' => $right_node,
                        'args' => new Node(ast\AST_ARG_LIST, 0, [$pipe_node->children['left']], $pipe_node->lineno),
                    ],
                    $pipe_node->lineno
                );
            }
            $arg_children = PartialApplication::getArgs($args_node);
            $arg_children[$placeholder_index] = $pipe_node->children['left'];
        } else {
            $arg_children = [$pipe_node->children['left']];
        }
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
