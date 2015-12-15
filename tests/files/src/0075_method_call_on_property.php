<?php
class A {
    public $p;
}
class C {
    function f(&$v) { $v = 42; }
}
function g(int $v) : int { return $v; }

$a = new A;
$a->p = new C;
$a->p->f($v);
g($v);
