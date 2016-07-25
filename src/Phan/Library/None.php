<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * @template T
 * The type of the element
 */
class None extends Option
{
    /**
     * Get a new instance of nothing
     */
    public function __construct()
    {
    }

    /**
     * @return bool
     */
    public function isDefined() : bool
    {
        return false;
    }

    /**
     * @param T $else
     * @return T
     */
    public function getOrElse($else)
    {
        return $else;
    }

    /**
     * @return T
     *
     * @suppress PhanTypeMissingReturn
     * This method will in all cases throw an exception without
     * returning a value
     */
    public function get()
    {
        throw new \Exception("Cannot call get on None");
    }

    /**
     * @return string
     * A string representation of this object
     */
    public function __tostring() : string
    {
        return 'None()';
    }
}
