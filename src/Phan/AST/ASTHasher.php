<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;

use function is_object;

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
    public static function hash(\ast\Node|float|int|null|string $node): string
    {
        if (!is_object($node)) {
            return \phan_ast_hash($node);
        }
        // Cache the hash on the node object to avoid recomputing
        // @phan-suppress-next-line PhanUndeclaredProperty
        return $node->hash ?? ($node->hash = \phan_ast_hash($node));
    }
}
