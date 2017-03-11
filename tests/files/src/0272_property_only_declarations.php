<?php

/**
 * @property \stdClass $x
 * @phan-forbid-undeclared-magic-properties
 */
class A272 {
    private $instances = [];
    /** @return mixed */
    public function __get($key) {
        return $this->instances[$key];
    }

    public static function test() {
        $x = new self();
        echo intdiv($x->x, 2);
        echo intdiv(new stdClass(), 2);
        echo intdiv($x->y, 2);  // no error because we don't know property y's type
    }
}
