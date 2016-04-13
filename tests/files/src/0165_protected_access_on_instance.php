<?php

class C1 {
    protected $p;
}

class C2 extends C1 {
    static function f(C2 $c) {
        $c->p = 2;
    }
}

$c = new C2();
C2::f($c);
