<?php
class A {
    public $p = 42;
}
$a = new A;
$a->p;

class B {
    public $p = 42;
    function __get($name) {}
}
$b = new B;
$b->p; // no-op access of public property
$b->q; // access via __get; may have side effects so not a no-op
