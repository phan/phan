<?php
class C286 {
    function f() {}
}

$v0 = new \stdClass;
$v0->p = 42;

$v1 = new \stdClass;
$v1->p = new C286();
$v1->p->f();
