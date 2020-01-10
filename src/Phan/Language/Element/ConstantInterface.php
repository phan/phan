<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Phan\Language\FQSEN;
use Phan\Language\FutureUnionType;
use Phan\Language\UnionType;

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
     */
    public function setFutureUnionType(FutureUnionType $future_union_type): void;

    /**
     * Sets the node with the AST representing the value of this constant.
     *
     * @param Node|string|float|int $node
     */
    public function setNodeForValue($node): void;

    /**
     * Gets the node with the AST representing the value of this constant.
     *
     * @return Node|string|float|int
     */
    public function getNodeForValue();

    /**
     * Gets the union type of this constant.
     */
    public function getUnionType(): UnionType;
}
