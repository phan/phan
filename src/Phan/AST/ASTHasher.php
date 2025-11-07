<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;

use function is_float;
use function is_int;
use function is_object;
use function is_string;

/**
 * This converts a PHP AST Node into a hash.
 * This ignores line numbers and spacing.
 *
 * Uses phan_ast_hash() which is provided by either:
 * - The phan_helpers C extension (fast XXH3-128)
 * - PHP polyfill (slower MD5, loaded via composer autoload)
 */
class ASTHasher
{
    /**
     * @param Node|string|int|float|null $node
     * @return string a 16-byte binary key for the Node which is unlikely to overlap for ordinary code
     */
    public static function hash(Node|float|int|null|string $node): string
    {
        static $string_hash_algo = null;
        if ($string_hash_algo === null) {
            $string_hash_algo = \in_array('xxh128', hash_algos(), true) ? 'xxh128' : 'md5';
        }
        // Handle primitives with raw representation (not hashed)
        if (!is_object($node)) {
            if (is_string($node)) {
                return hash($string_hash_algo, $node, true);
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

        // Cache the hash on the node object to avoid recomputing
        // @phan-suppress-next-line PhanUndeclaredProperty
        return $node->hash ?? ($node->hash = \phan_ast_hash($node));
    }
}
