<?php

class C45 {
    private $x = 42;
    public function f() : int {
        return $this->x;
    }
}

$v = C45::f();
