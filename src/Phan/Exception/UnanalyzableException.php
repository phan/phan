<?php declare(strict_types=1);
namespace Phan\Exception;

use \Phan\Debug;
use \ast\Node;

class UnanalyzableException extends \Exception {

    /**
     * @var Node
     */
    private $node;

    /**
     * @param Node $node
     * The node causing the exception
     *
     * @param string $message
     * The error message
     */
    public function __construct(
        Node $node,
        string $message = null
    ) {
        parent::__construct($message);
        $this->node = $node;
    }

    /**
     * @return Node
     * The node for which we have an exception
     */
    public function getNode() : Node {
        return $this->node;
    }
}
