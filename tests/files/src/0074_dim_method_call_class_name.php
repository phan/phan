<?php
class A {
    public function g() {}
}
class B {
    /** @var A[] */
    public $p;

    public function __construct() {
        $this->p[] = new A;  // Warns because this isn't an array yet.
    }

    public function f() {
        $this->p[0]->g();
    }

    public function h() {
        $this->p = null;
        $this->p[] = new A();
    }
}
(new B)->f();
