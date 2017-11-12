<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * A tuple of 4 elements.
 *
 * @template T0
 * The type of element zero
 *
 * @template T1
 * The type of element one
 *
 * @template T2
 * The type of element one
 *
 * @template T3
 * The type of element one
 *
 * @inherits Tuple3<T0, T1, T2>
 */
class Tuple4 extends Tuple3
{
    /** @var int */
    const ARITY = 4;

    /** @var T3 */
    public $_3;

    /**
     * @param T0 $_0
     * The 0th element
     *
     * @param T1 $_1
     * The 1st element
     *
     * @param T2 $_2
     * The 2nd element
     *
     * @param T3 $_3
     * The 3rd element
     */
    public function __construct($_0, $_1, $_2, $_3)
    {
        parent::__construct($_0, $_1, $_2);
        $this->_3 = $_3;
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
            $this->_2,
            $this->_3,
        ];
    }
}
