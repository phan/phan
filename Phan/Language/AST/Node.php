<?php declare(strict_types=1);
namespace Phan\Language\AST\Node;

abstract class Node {
    /** @var Node */
    private $node;

    /**
     * @param Node $node
     * An AST node
     */
    public function __construct(Node $node) {
        $this->node = $node;
    }

    /**
     * @return Node
     */
    public function getNode() {
        return $this->node;
    }

}
