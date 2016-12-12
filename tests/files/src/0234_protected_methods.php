<?php
class A410 {
    public function __construct() {
        $v = new B410();
        $v->f();
        $v->g();
    }

    protected function f() {
        return 5;
    }

    private function g() {
        return 5;
    }
}
class B410 extends A410 {}
new A410;
