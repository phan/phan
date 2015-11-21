<?php

class A {
    const c = 'constant';
    public $p = 'property';
}

class B extends A {
}

$b = new B;

$cc = B::c;
$pp = $b->p;

print "$cc $pp\n";

$not_found = $b->x;
$no_found = A::y;
