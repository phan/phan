<?php declare(strict_types=1);
namespace Phan\Library;

/**
 * A map from object to object with key comparisons
 * based on spl_object_hash.
 */
class Map extends \SplObjectStorage
{

    /**
     * We redefine the key to be the actual key rather than
     * the index of the key
     *
     * @return object
     * @suppress PhanParamSignatureMismatchInternal - This is deliberately changing the phpdoc return type.
     */
    public function key()
    {
        return parent::current();
    }

    /**
     * We redefine the current value to the current value rather
     * than the current key
     */
    public function current()
    {
        return $this->offsetGet(parent::current());
    }

    /**
     * @param \Closure $key_closure
     * A closure that maps each key of this map
     * to a new key
     *
     * @param \Closure $value_closure
     * A closure that maps each value of this map
     * to a new value.
     *
     * @return Map
     * A new map containing the mapped keys and
     * values
     */
    public function keyValueMap(\Closure $key_closure, \Closure $value_closure)
    {
        $map = new Map;
        foreach ($this as $key => $value) {
            $map[$key_closure($key)] = $value_closure($value);
        }
        return $map;
    }

    /**
     * @return Map
     * A new map with each key and value cloned
     */
    public function deepCopy() : Map
    {
        $clone = function ($element) {
            return clone($element);
        };
        return $this->keyValueMap($clone, $clone);
    }

    /**
     * @return Set
     * A new set with the unique values from this map.
     * Precondition: values of this map are objects.
     */
    public function valueSet() : Set
    {
        $set = new Set();
        foreach ($this as $value) {
            $set->attach($value);
        }
        return $set;
    }

    /**
     * @return Set
     * A new set with the unique keys from this map.
     * Precondition: values of this set are objects.
     */
    public function keySet() : Set
    {
        $set = new Set();
        foreach ($this as $key => $_) {
            $set->attach($key);
        }
        return $set;
    }
}
