<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * A set of objects supporting union and
 * intersection
 */
class Set extends \SplObjectStorage
{

    /**
     * @param \Iterator|array $element_iterator
     * An optional set of items to add to the set
     */
    public function __construct($element_iterator = null)
    {
        foreach ($element_iterator ?? [] as $element) {
            $this->attach($element);
        }
    }

    /**
     * @return array
     * An array of all elements in the set is returned
     */
    public function toArray() : array
    {
        return \iterator_to_array($this);
    }

    /**
     * @param Set $other
     * A set of items to intersect with this set
     *
     * @return Set
     * A new set which contains only items in this
     * Set and the given Set
     */
    public function intersect(Set $other) : Set
    {
        $set = new Set();
        foreach ($this as $element) {
            if ($other->contains($element)) {
                $set->attach($element);
            }
        }
        return $set;
    }

    /**
     * @param Set[] $set_list
     * A list of sets to intersect
     *
     * @return Set
     * A new Set containing only the elements that appear in
     * all parameters
     */
    public static function intersectAll(array $set_list) : Set
    {
        if (empty($set_list)) {
            return new Set();
        }

        $intersected_set = \array_shift($set_list);
        foreach ($set_list as $set) {
            $intersected_set = $intersected_set->intersect($set);
        }

        return $intersected_set;
    }

    /**
     * @param Set $other
     * A set of items to union with this set
     *
     * @return Set
     * A new set which contains only items in this
     * Set and the given Set.
     *
     * @suppress PhanUnreferencedPublicMethod
     */
    public function union(Set $other) : Set
    {
        $set = new Set();
        $set->addAll($this);
        $set->addAll($other);
        return $set;
    }

    /**
     * @param Set[] $set_list
     * A list of sets to intersect
     *
     * @return Set
     * A new Set containing any element that appear in
     * any parameters
     */
    public static function unionAll(array $set_list) : Set
    {
        if (empty($set_list)) {
            return new Set();
        }

        $union_set = \array_shift($set_list);
        foreach ($set_list as $set) {
            $union_set = $union_set->union($set);
        }

        return $union_set;
    }


    /**
     * @return bool
     * True if this set contains any elements in the given list
     */
    public function containsAny(array $element_list) : bool
    {
        foreach ($element_list as $element) {
            if ($this->contains($element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Closure $closure
     * A closure taking a set element that returns a boolean
     * for which true will cause the element to be retained
     * and false will cause the element to be removed
     *
     * @return Set
     * A new set for which all elements when passed to the given
     * closure return true
     */
    public function filter(\Closure $closure)
    {
        $set = new Set();
        foreach ($this as $element) {
            if ($closure($element)) {
                $set->attach($element);
            }
        }
        return $set;
    }

    /**
     * @param \Closure $closure
     * A closure that maps each element of this set
     * to a new element
     *
     * @return Set
     * A new set containing the mapped values
     */
    public function map(\Closure $closure) : Set
    {
        $set = new Set;
        foreach ($this as $element) {
            $set->attach($closure($element));
        }
        return $set;
    }

    /**
     * @return Set
     * A new set with each element cloned
     */
    public function deepCopy() : Set
    {
        return $this->map(function ($element) {
            return clone($element);
        });
    }

    /**
     * @param \Closure $closure
     * A closure that takes an element and returns a boolean
     *
     * @return mixed|bool
     * The first element for which the given closure returns
     * true is returned or false if no elements pass the
     * given closure
     */
    public function find(\Closure $closure)
    {
        foreach ($this as $element) {
            if ($closure($element)) {
                return $element;
            }
        }
        return false;
    }

    /**
     * @return string
     * A string representation of this set for use in
     * debugging
     */
    public function __toString() : string
    {
        $string = '['
            . \implode(',', \array_map(function ($element) {
                return (string)$element;
            }, \iterator_to_array($this)))
            . ']';

        return $string;
    }
}
