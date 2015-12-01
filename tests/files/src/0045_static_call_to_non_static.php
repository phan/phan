<?php

class C {
    private $x = 42;
    public function f() : int {
        return $this->x;
    }
}

$v = C::f();
