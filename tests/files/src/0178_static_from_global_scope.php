<?php

class C178 {
    /** @return static */
    static function instance() {
        return new static;
    }

    /** @return static */
    function f() {
        return $this;
    }

}

class D178 extends C178 {}

class E178 {}
function g(E178 $p) {}

g(D178::instance()->f());
g(C178::instance()->f());
