<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * @template T
 * The type of the element
 *
 * @inherits Option<T>
 */
class Some extends Option
{
    /** @var T */
    private $_;

    /**
     * @param T $_
     */
    public function __construct($_)
    {
        $this->_ = $_;
    }

    /**
     * @return bool
     */
    public function isDefined() : bool
    {
        return true;
    }

    /**
     * @return T
     */
    public function get()
    {
        return $this->_;
    }

    /**
     * @param T $else used in the None sibling class (@phan-unused-param)
     * @return T
     */
    public function getOrElse($else)
    {
        return $this->get();
    }

    /**
     * @return string
     * A string representation of this object
     */
    public function __tostring() : string
    {
        return 'Some(' . $this->_ . ')';
    }
}
