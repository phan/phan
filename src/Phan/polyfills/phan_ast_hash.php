<?php

declare(strict_types=1);

use ast\Node;

/**
 * Polyfill for phan_ast_hash() when the phan_helpers extension is not available.
 *
 * This function generates a 16-byte hash (XXH3-128 when available, otherwise MD5) of an AST node,
 * ignoring line numbers and spacing to detect semantically identical code.
 *
 * @param Node|string|int|float|null $node
 * @return string 16-byte binary hash
 * @suppress PhanRedefineFunctionInternal,UnusedSuppression
 */
function phan_ast_hash(Node|string|int|float|null $node): string
{
    static $hash_algo = null;
    if ($hash_algo === null) {
        $hash_algo = \in_array('xxh128', hash_algos(), true) ? 'xxh128' : 'md5';
    }
    // Handle non-objects (primitives)
    if (!is_object($node)) {
        if (is_string($node)) {
            return hash($hash_algo, $node, true);
        } elseif (is_int($node)) {
            if (\PHP_INT_SIZE >= 8) {
                return "\0\0\0\0\0\0\0\0" . \pack('J', $node);
            } else {
                return "\0\0\0\0\0\0\0\0\0\0\0\0" . \pack('N', $node);
            }
        } elseif (is_float($node)) {
            return "\0\0\0\0\0\0\0\1" . \pack('e', $node);
        } else {
            // $node must be null
            return "\0\0\0\0\0\0\0\2\0\0\0\0\0\0\0\0";
        }
    }

    // Handle AST nodes
    $ctx = hash_init($hash_algo);
    hash_update($ctx, 'N');
    hash_update($ctx, (string)$node->kind);
    hash_update($ctx, ':');
    hash_update($ctx, (string)($node->flags & 0x3ffffff));
    foreach ($node->children as $key => $child) {
        // Skip keys starting with "phan" (added by PhanAnnotationAdder)
        if (\is_string($key) && \strncmp($key, 'phan', 4) === 0) {
            continue;
        }

        // Hash the key
        if (is_string($key)) {
            hash_update($ctx, hash($hash_algo, $key, true));
        } elseif (is_int($key)) {
            if (\PHP_INT_SIZE >= 8) {
                hash_update($ctx, "\0\0\0\0\0\0\0\0" . \pack('J', $key));
            } else {
                hash_update($ctx, "\0\0\0\0\0\0\0\0\0\0\0\0" . \pack('N', $key));
            }
        } else {
            hash_update($ctx, hash($hash_algo, (string)$key, true));
        }

        // Hash the child value (recursive)
        hash_update($ctx, phan_ast_hash($child));
    }

    return hash_final($ctx, true);
}
