<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;
use WeakMap;

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
     * The hashes of the nodes that were hashed, which are removed when the node is freed.
     *
     * This is not a dynamic property of the node: PHP allocates a property table of 376 bytes for the first
     * dynamic property of an object, and an entry of a WeakMap takes 105 bytes.
     * Most of the nodes that get hashed have no other dynamic property (https://github.com/phan/phan/issues/5638)
     *
     * @var ?WeakMap<Node,string>
     */
    private static $node_hashes = null;

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

        // Cache the hash of the node to avoid recomputing it.
        // A clone of a node is a different key, so it does not inherit the hash of the node it was cloned from.
        $node_hashes = self::$node_hashes ??= new WeakMap();
        return $node_hashes[$node] ??= \phan_ast_hash($node);
    }
}
