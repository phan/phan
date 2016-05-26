<?php

class C177_0 {}

class C177_1 {
    /** @return static */
    public static function f() {
        return new static;
    }
}

class C177_2 extends C177_1 {}

function f(C177_0 $p) : C177_0 {
    return $p;
}

f(C177_2::f());
f(C177_1::f());
