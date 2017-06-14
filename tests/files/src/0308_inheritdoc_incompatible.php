<?php

class MyMap308 extends SplObjectStorage {
    /**
     * @return object - This is deliberately incompatible
     */
    public function key() {
        return self::current();
    }

    /**
     * @return bool - This is deliberately incompatible
     * @suppress PhanParamSignatureMismatchInternal (This suppression should work)
     */
    public function rewind() {
        parent::rewind();
        return true;
    }
}
