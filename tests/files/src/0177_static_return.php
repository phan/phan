<?php

class C0 {}

class C1 {
    /** @return static */
    public static function f() {
        return new static;
    }
}

class C2 extends C1 {}

function f(C0 $p) : C0 {
    return $p;
}

f(C2::f());
f(C1::f());
