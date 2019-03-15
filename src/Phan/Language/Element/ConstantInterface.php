<?php declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Phan\Language\FQSEN;
use Phan\Language\FutureUnionType;

/**
 * Represents APIs used when Phan is setting up/analyzing
 * the representation of a given global or class constant.
 * @method FQSEN getFQSEN() return type covariance isn't supported in php 7.0, I think
 */
interface ConstantInterface
{

    /**
     * Sets a value that can be used to resolve the union type of this constant later.
     * Used if it cannot be resolved immediately while parsing.
     *
     * @return void
     */
    public function setFutureUnionType(FutureUnionType $future_union_type);

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
