<?php
use Iterator;

class ScalarType531 {}

class Collection531 implements Iterator
{

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var int
     */
    protected $index = 0;

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array $items
     * @return static
     */
    public function setItems(array $items)
    {
        $this->items = $items;
        return $this;
    }

    /**
     * @param mixed $item
     * @return $this
     */
    public function addItem($item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Return the current element
     *
     * @link  http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return @$this->items[$this->index];
    }

    /**
     * Move forward to next element
     *
     * @link  http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        $this->index++;
    }

    /**
     * Return the key of the current element
     *
     * @link  http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * Checks if current position is valid
     *
     * @link  http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return $this->index < count($this->items);
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @link  http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        $this->index = 0;
    }
}
/**
 * Class ScalarTypeCollection531
 *
 * @method array getItems()
 * @method array addItem(ScalarType531 $item)
 * @method array setItems(ScalarType531[] $item)
 */
class ScalarTypeCollection531 extends Collection531
{

    /**
     * @var ScalarType531[]
     */
    protected $items = [];

    /**
     * ScalarTypeCollection531 constructor.
     *
     * @param ScalarType531[] $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }
}
