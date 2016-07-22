<?php declare(strict_types=1);
namespace Phan\Library;

/**
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
