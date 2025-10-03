<?php

declare(strict_types=1);

use ast\Node;

/**
 * Polyfill for phan_ast_hash() when the phan_helpers extension is not available.
 *
 * This function generates a 16-byte MD5 hash of an AST node, ignoring line numbers
 * and spacing to detect semantically identical code.
 *
 * @param Node|string|int|float|null $node
 * @return string 16-byte binary hash
 * @suppress PhanRedefineFunctionInternal
 */
function phan_ast_hash(Node|string|int|float|null $node): string
{
    // Handle non-objects (primitives)
    if (!is_object($node)) {
        if (is_string($node)) {
            return md5($node, true);
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
    $str = 'N' . $node->kind . ':' . ($node->flags & 0x3ffffff);
    foreach ($node->children as $key => $child) {
        // Skip keys starting with "phan" (added by PhanAnnotationAdder)
        if (\is_string($key) && \strncmp($key, 'phan', 4) === 0) {
            continue;
        }

        // Hash the key
        if (is_string($key)) {
            $str .= md5($key, true);
        } elseif (is_int($key)) {
            if (\PHP_INT_SIZE >= 8) {
                $str .= "\0\0\0\0\0\0\0\0" . \pack('J', $key);
            } else {
                $str .= "\0\0\0\0\0\0\0\0\0\0\0\0" . \pack('N', $key);
            }
        } else {
            $str .= md5((string) $key, true);
        }

        // Hash the child value (recursive)
        $str .= phan_ast_hash($child);
    }

    return md5($str, true);
}
