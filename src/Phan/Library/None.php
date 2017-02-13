<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * @inherits Option<null>
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
     * @param mixed $else
     * @return mixed
     */
    public function getOrElse($else)
    {
        return $else;
    }

    /**
     * @return null
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
