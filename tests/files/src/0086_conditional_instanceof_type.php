<?php
class A {
    function f(A $p) {}
}

$x = null;

$x->f($x);

if ($x instanceof A) {
    $x->f($x);
}

$x->f($x);
