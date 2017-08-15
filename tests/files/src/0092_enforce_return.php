<?php

class A {
    public function f() : int {
    }

    public function g() : int {
        return 42;
    }

    public function m() : int {
        throw new \Exception();
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
function l() : int {
    throw new \Exception();
}
function i92() : int {
    if (rand() % 2) {
        throw new \Exception();
    } else {
        throw new \Exception();
    }
}
function j92() : int {
    if (rand() % 2) {
        throw new \Exception();
    } else;
}
