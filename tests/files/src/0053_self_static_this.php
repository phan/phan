<?php

class C {
    /**
     * @return self
     */
    function f() {
        return $this;
    }

    /**
     * @return static
     */
    function g() {
        return $this;
    }

    /**
     * @return $this
     */
    function h() {
        return $this;
    }

    function test(C $c) {}
}

$c = new C;
$f = $c->test($c->f());
$g = $c->test($c->g());
$h = $c->test($c->h());
