<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * A tuple of 3 elements.
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
 * @inherits Tuple2<T0, T1>
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Tuple3 extends Tuple2
{
    /** @var int */
    public const ARITY = 3;

    /** @var T2 element 2 of this tuple (0-based index) */
    public $_2;

    /**
     * @param T0 $_0
     * The 0th element
     *
     * @param T1 $_1
     * The 1st element
     *
     * @param T2 $_2
     * The 2nd element
     */
    public function __construct($_0, $_1, $_2)
    {
        $this->_0 = $_0;
        $this->_1 = $_1;
        $this->_2 = $_2;
    }

    /**
     * @return array{0:T0,1:T1,2:T2}
     * An array of all elements in this tuple.
     */
    public function toArray(): array
    {
        return [
            $this->_0,
            $this->_1,
            $this->_2,
        ];
    }
}
