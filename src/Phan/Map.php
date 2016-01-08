<?php declare(strict_types=1);
namespace Phan;

/**
 * A map from object to object with key comparisons
 * based on spl_object_hash, which I believe its the zval's
 * memory address.
 */
class Map extends \SplObjectStorage {

    /**
     * We redefine the key to be the actual key rather than
     * the index of the key
     */
    public function key() {
        return parent::current();
    }

    /**
     * We redefine the current value to the current value rather
     * than the current key
     */
    public function current() {
        return $this->offsetGet(parent::current());
    }

}
