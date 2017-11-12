<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * A tuple of 2 elements.
 *
 * @template T0
 * The type of element zero
 *
 * @template T1
 * The type of element one
 *
 * @inherits Tuple1<T0>
 */
class Tuple2 extends Tuple1
{
    /** @var int */
    const ARITY = 2;

    /** @var T1 */
    public $_1;

    /**
     * @param T0 $_0
     * The 0th element
     *
     * @param T1 $_1
     * The 1st element
     */
    public function __construct($_0, $_1)
    {
        parent::__construct($_0);
        $this->_1 = $_1;
    }

    /**
     * @return array
     * An array of all elements in this tuple.
     */
    public function toArray() : array
    {
        return [
            $this->_0,
            $this->_1,
        ];
    }
}
