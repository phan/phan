<?php
namespace NS630;

class AbstractNode {}

/**
 * Recursive iterator for node object graphs.
 */
final class Iterator implements \RecursiveIterator
{
    /**
     * @var int
     */
    private $position;

    /**
     * @var AbstractNode[]
     */
    private $nodes;

    public function __construct()
    {
        $this->nodes = [];
    }

    /**
     * Rewinds the Iterator to the first element.
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if there is a current element after calls to rewind() or next().
     */
    public function valid(): bool
    {
        return $this->position < \count($this->nodes);
    }

    /**
     * Returns the key of the current element.
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Returns the current element.
     */
    public function current(): AbstractNode
    {
        return $this->valid() ? $this->nodes[$this->position] : null;
    }

    public function next(): void
    {
        $this->position++;
    }

    /**
     * @return Iterator
     */
    public function getChildren(): self
    {
        return new self();
    }

    public function hasChildren(): bool
    {
        return false;
    }
}
