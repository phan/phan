<?php

class A {
    public function f() : int {
    }

    public function g() : int {
        return 42;
    }
}

abstract class B {
    abstract function f() : int;
    abstract function g();
}

function h() : int {}
function j() {}
function k() : int {
    return 42;
}
