<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;

use function is_float;
use function is_int;
use function is_null;
use function is_object;
use function is_string;
use function md5;

/**
 * This converts a PHP AST Node into a hash.
 * This ignores line numbers and spacing.
 */
class ASTHasher
{
    /**
     * @param string|int|null $node
     * @return string a 16-byte binary key for the array key
     * @internal
     */
    public static function hashKey($node): string
    {
        if (is_string($node)) {
            return md5($node, true);
        } elseif (is_int($node)) {
            if (\PHP_INT_SIZE >= 8) {
                return "\0\0\0\0\0\0\0\0" . \pack('J', $node);
            } else {
                return "\0\0\0\0\0\0\0\0\0\0\0\0" . \pack('N', $node);
            }
        }
        // This is not a valid array key, give up
        return md5((string) $node, true);
    }

    /**
     * @param Node|string|int|float|null $node
     * @return string a 16-byte binary key for the Node which is unlikely to overlap for ordinary code
     */
    public static function hash($node): string
    {
        if (!is_object($node)) {
            // hashKey
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
            } elseif (is_null($node)) {
                return "\0\0\0\0\0\0\0\2\0\0\0\0\0\0\0\0";
            }
            // This is not a valid AST, give up
            return md5((string) $node, true);
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
            if (\is_string($key) && \strncmp($key, 'phan', 4) === 0) {
                continue;
            }
            $str .= self::hashKey($key);
            $str .= self::hash($child);
        }
        return md5($str, true);
    }
}
