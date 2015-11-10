<?php

class A {
    function f() : int {
        return 42;
    }
}

class B {
    function g() : string {
        return 'string';
    }
}

$a = new A;
print $a->g();
print $a->f();

$a = new B;
print $a->g();
print $a->f();
