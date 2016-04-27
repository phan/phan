<?php

class C {
    /** @return static */
    static function instance() {
        return new static;
    }

    /** @return static */
    function f() {
        return $this;
    }

}

class D extends C {}

class E {}
function g(E $p) {}

g(D::instance()->f());
g(C::instance()->f());
