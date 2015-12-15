<?php
class A {
    public function g() {}
}
class B {
    /** @var A[] */
    public $p;

    public function __construct() {
        $this->p[] = new A;
    }

    public function f() {
        $this->p[0]->g();
    }
}
(new B)->f();
