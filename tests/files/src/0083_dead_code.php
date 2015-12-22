<?php

class A {
    public $p = null;
    const C = null;
    public function f() {}
}

class B {
    public $p = null;
    const C = null;
    public function f() {}
}

$b = new B;
$b->p = B::C;
$b->f();
