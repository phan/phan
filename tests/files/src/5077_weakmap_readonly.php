<?php

/**
 * PhanAccessReadOnlyProperty issue for array access on property implements ArrayAccess interface like Weakmap
 */
class Demo {
    private readonly WeakMap $map;
    public function __construct() {
        $this->map = new WeakMap;
    }

    public function doSomething() {
        $o = new stdClass;
        $this->map[$o] = new stdClass;
    }
}
