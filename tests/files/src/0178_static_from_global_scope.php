<?php

class C {
    /**
     * @return static
     */
    static function instance() {
        return new static;
    }

    function f() : int {
        return 42;
    }

}

class D extends C {
}

function g(string $p) {}

g(D::instance()->f());
g(C::instance()->f());
