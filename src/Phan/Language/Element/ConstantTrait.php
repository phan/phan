<?php declare(strict_types=1);
namespace Phan\Language\Element;

use ast\Node;

trait ConstantTrait
{
    /** @var Node|string|float|int */
    protected $defining_node;

    use ElementFutureUnionType;

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    abstract public function getName() : string;

    public function __toString() : string
    {
        return 'const ' . $this->getName();
    }

    /**
     * Sets the node with the AST representing the value of this constant.
     *
     * @param Node|string|float|int $node Either a node or a constant to be used as the value of the constant.
     * @return void
     */
    public function setNodeForValue($node)
    {
        $this->defining_node = $node;
    }

    /**
     * Gets the node with the AST representing the value of this constant.
     *
     * @return Node|string|float|int
     */
    public function getNodeForValue()
    {
        return $this->defining_node;
    }
}
