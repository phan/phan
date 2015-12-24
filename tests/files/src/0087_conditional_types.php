<?php
class A {
    function f() {}
}
class B {
    function g() {}
}
$v = new A;
if (rand(0,1) > 0) {
    $v = new B;
    $v->g();
} else {
    $v->f();
}

if (rand(0,1) > 0) {
    $v = new A;
    $v->g();
} else {
    $v = new B;
    $v->f();
}

