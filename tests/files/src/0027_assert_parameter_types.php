<?php
// This test validates phan checking the types of params passed to assert()
class A {
    public static function f() : bool {
        return false;
    }
}

assert(!A::f(), 'string');

assert(10 > 1, 'string');
