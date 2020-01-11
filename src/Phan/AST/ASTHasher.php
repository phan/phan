<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;

use function is_int;
use function is_string;
use function md5;

/**
 * This converts a PHP AST Node into a hash.
 * This ignores line numbers and spacing.
 */
class ASTHasher
{
    /**
     * @param string|int|float|null $node
     * @return string a 16-byte binary key for the array key
     */
    public static function hashKey($node): string
    {
        if (is_string($node)) {
            return md5('s' . $node, true);
        }
        // Both 2.0 and 2 cast to the string '2'
        if (is_int($node)) {
            return md5((string) $node, true);
        }
        return md5('f' . $node, true);
    }

    /**
     * @param Node|string|int|float|null $node
     * @return string a 16-byte binary key for the Node
     */
    public static function hash($node): string
    {
        if (!($node instanceof Node)) {
            // hashKey
            if (is_string($node)) {
                return md5('s' . $node, true);
            }
            if (is_int($node)) {
                return md5((string) $node, true);
            }
            return md5('f' . $node, true);
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        return $node->hash ?? ($node->hash = self::computeHash($node));
    }

    /**
     * @param Node $node
     * @return string a newly computed 16-byte binary key
     */
    private static function computeHash(Node $node): string
    {
        $str = 'N' . $node->kind . ':' . ($node->flags & 0xfffff);
        foreach ($node->children as $key => $child) {
            // added in PhanAnnotationAdder
            if ($key === 'phan_nf') {
                continue;
            }
            $str .= self::hashKey($key);
            $str .= self::hash($child);
        }
        return md5($str, true);
    }
}
