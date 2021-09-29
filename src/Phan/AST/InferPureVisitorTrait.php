<?php

declare(strict_types=1);

namespace Phan\AST;

use ast\Node;
use Phan\Exception\NodeException;

/**
 * Contains methods to reduce boilerplate
 */
trait InferPureVisitorTrait
{
    /**
     * Throws NodeException unconditionally
     * @return never
     * @throws NodeException
     * @suppress PhanUnreferencedPublicMethod https://github.com/phan/phan/issues/4558
     */
    public function throwNodeException(Node $node): void
    {
        throw new NodeException($node);
    }

    /**
     * Calls on all child nodes on $node that are instances of Node.
     */
    public function maybeInvokeAllChildNodes(Node $node): void
    {
        foreach ($node->children as $c) {
            if ($c instanceof Node) {
                // @phan-suppress-next-line PhanUndeclaredMethod
                $this->__invoke($c);
            }
        }
    }
}
