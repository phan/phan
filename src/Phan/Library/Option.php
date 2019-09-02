<?php declare(strict_types=1);

namespace Phan\Library;

/**
 * An Option is a safer equivalent to nullable values, indicating that callers should check for absences of values.
 * This demonstrates Phan's template support.
 *
 * It is either `None` (analogous to `null`) or `Some<T>` (analogous to `T`).
 *
 * This was introduced prior to Phan's support for nullable types and strict type checking.
 *
 * @template T
 * The type of the element
 * @phan-pure
 */
abstract class Option
{
    /**
     * If this has a value, return that value.
     * Otherwise, return $else
     *
     * @param T $else
     * @return T
     */
    abstract public function getOrElse($else);

    /**
     * @return bool true if this is defined (i.e. this is an instance of Some)
     */
    abstract public function isDefined() : bool;

    /**
     * Gets the value, or throws if this was an instance of None.
     *
     * The caller should check if $this->isDefined()
     *
     * @return T
     */
    abstract public function get();
}
