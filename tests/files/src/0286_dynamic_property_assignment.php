<?php
class C286 {
    function f() {}
}

$v286a = new \stdClass;
$v286a->p286 = 42;

$v286b= new \stdClass;
$v286b->p286 = new C286();
$v286b->p286->f();
