<?php declare(strict_types=1);

namespace Phan\Library;

/**
 * An abstract tuple.
 */
abstract class Tuple
{
    const ARITY = 0;

    /**
     * @return int
     * The arity of this tuple
     * @suppress PhanUnreferencedPublicMethod potentially used in the future
     */
    public function arity() : int
    {
        return static::ARITY;
    }

    /**
     * @return array{}
     * An array of all elements in this tuple.
     */
    abstract public function toArray() : array;
}
