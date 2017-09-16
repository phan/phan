<?php declare(strict_types=1);
namespace Phan;

use ast\Node;

/**
 * Utilities used for things other than debugging.
 */
class Util
{
    /**
     * @param ?Node $node
     * @return ?int
     */
    public static function getEndLineno($node)
    {
        return $node->endLineno ?? null;
    }
}
