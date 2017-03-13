<?php

/**
 * @property-read  \stdClass $x
 * @property-write \stdClass $y
 */
class A273 {
    private $instances = [];
    /** @return mixed */
    public function __get($key) {
        return $this->instances[$key];
    }

    public function __set($key, $value) {
        $this->instances[$key] = $value;
    }

    public static function test() {
        $x = new self();
        $a = $x->x;  // read stdClass
        echo intdiv($a, 2);  // use in wrong context
        $x->y = 2;  // assign incompatible type to stdClass
    }
}
