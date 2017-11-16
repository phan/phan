<?php declare(strict_types=1);
namespace Phan\Exception;

use ast\Node;

class NodeException extends \Exception
{

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
        string $message = ''
    ) {
        parent::__construct($message);
        $this->node = $node;
    }

    /**
     * @return Node
     * The node for which we have an exception
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getNode() : Node
    {
        return $this->node;
    }
}
