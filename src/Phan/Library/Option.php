<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * An Option is a safer equivalent to nullable values, indicating that callers should check for absenses of values.
 * This demonstrates Phan's template support.
 *
 * It is either `None` (analogous to `null`) or `Some<T>` (analogous to `T`).
 *
 * This was introduced prior to Phan's support for nullable types and strict type checking.
 *
 * @template T
 * The type of the element
 */
abstract class Option
{
    /**
     * @param T $else
     * @return T
     */
    abstract public function getOrElse($else);

    /**
     * @return bool
     */
    abstract public function isDefined() : bool;

    /**
     * @return T
     */
    abstract public function get();
}
