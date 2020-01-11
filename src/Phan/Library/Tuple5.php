<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * A tuple of 5 elements.
 *
 * @template T0
 * The type of element zero
 *
 * @template T1
 * The type of element one
 *
 * @template T2
 * The type of element two
 *
 * @template T3
 * The type of element three
 *
 * @template T4
 * The type of element four
 *
 * @inherits Tuple4<T0, T1, T2, T3>
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Tuple5 extends Tuple4
{
    /** @var int */
    public const ARITY = 4;

    /** @var T4 element 4 of this tuple (0-based index) */
    public $_4;

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
     *
     * @param T4 $_4
     * The 4th element
     */
    public function __construct($_0, $_1, $_2, $_3, $_4)
    {
        $this->_0 = $_0;
        $this->_1 = $_1;
        $this->_2 = $_2;
        $this->_3 = $_3;
        $this->_4 = $_4;
    }

    /**
     * @return array{0:T0,1:T1,2:T2,3:T3,4:T4}
     * An array of all elements in this tuple.
     */
    public function toArray(): array
    {
        return [
            $this->_0,
            $this->_1,
            $this->_2,
            $this->_3,
            $this->_4,
        ];
    }
}
