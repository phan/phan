<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Language\FutureUnionType;
use ast\Node;

interface ConstantInterface
{

    /**
     * @return void
     */
    public function setFutureUnionType(
        FutureUnionType $future_union_type
    );

    /**
     * Sets the node with the AST representing the value of this constant.
     *
     * @param Node|string|float|int $node
     * @return void
     */
    public function setNodeForValue($node);

    /**
     * Gets the node with the AST representing the value of this constant.
     *
     * @return Node|string|float|int
     */
    public function getNodeForValue();
}
