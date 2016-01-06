<?php declare(strict_types=1);
namespace Phan;

/**
 * A set of objects supporting union and
 * intersection
 */
class Set extends \SplObjectStorage {

    /**
     * @param object[] $elements
     * An optional set of items to add to the set
     */
    public function __construct($elements = null) {
        foreach ($elements ?? [] as $element) {
            $this->attach($element);
        }
    }

    /**
     * @param Set $other
     * A set of items to intersect with this set
     *
     * @return Set
     * A new set which contains only items in this
     * Set and the given Set
     */
    public function intersect(Set $other) : Set {
        $set = new Set();
        foreach ($this as $element) {
            print spl_object_hash($element)."\n";
            if ($other->contains($element)) {
                $set->attach($element);
            }
        }
        return $set;
    }

    /**
     * @param Set $other
     * A set of items to union with this set
     *
     * @return Set
     * A new set which contains only items in this
     * Set and the given Set.
     */
    public function union(Set $other) : Set {
        $set = new Set();
        $set->addAll($this);
        $set->addAll($other);
        return $set;
    }

    /**
     * @return string
     * A string representation of this set for use in
     * debugging
     */
    public function __toString() : string {
        $string = '['
            . implode(',', array_map(function($element) {
                return (string)$element;
            }, iterator_to_array($this)))
            . ']';

        return $string;
    }

}
