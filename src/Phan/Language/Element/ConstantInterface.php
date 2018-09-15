<?php declare(strict_types=1);
namespace Phan\Language\Element;

use ast\Node;
use Phan\Language\FutureUnionType;

/**
 * Represents APIs used when Phan is setting up/analyzing
 * the representation of a given global or class constant.
 */
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
