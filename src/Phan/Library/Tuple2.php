<?php

declare(strict_types=1);

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
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Tuple2 extends Tuple1
{
    /** @var int */
    public const ARITY = 2;

    /** @var T1 element 1 of this tuple (0-based index) */
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
        $this->_0 = $_0;
        $this->_1 = $_1;
    }

    /**
     * @return array{0:T0,1:T1}
     * An array of all elements in this tuple.
     */
    public function toArray(): array
    {
        return [
            $this->_0,
            $this->_1,
        ];
    }
}
