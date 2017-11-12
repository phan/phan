<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * A tuple of 1 element.
 *
 * @template T0
 * The type of element zero
 */
class Tuple1 extends Tuple
{
    /** @var int */
    const ARITY = 1;

    /** @var T0 */
    public $_0;

    /**
     * @param T0 $_0
     * The 0th element
     */
    public function __construct($_0)
    {
        $this->_0 = $_0;
    }

    /**
     * @return int
     * The arity of this tuple
     */
    public function arity() : int
    {
        return static::ARITY;
    }

    /**
     * @return array
     * An array of all elements in this tuple.
     */
    public function toArray() : array
    {
        return [
            $this->_0,
        ];
    }
}
