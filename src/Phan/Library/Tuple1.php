<?php

declare(strict_types=1);

namespace Phan\Library;

/**
 * A tuple of 1 element.
 *
 * @template T0
 * The type of element zero
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Tuple1 extends Tuple
{
    /** @var int */
    public const ARITY = 1;

    /** @var T0 element 0 of this tuple (0-based index) */
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
    public function arity(): int
    {
        return static::ARITY;
    }

    /**
     * @return array{0:T0}
     * An array of all elements in this tuple.
     */
    public function toArray(): array
    {
        return [
            $this->_0,
        ];
    }
}
