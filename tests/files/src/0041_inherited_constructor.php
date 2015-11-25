<?php

class A {
    public function __construct() {
    }
}

class B {
    public $p = 'p';
}

function f(B $b) : B {
    return $b;
}

$a = f(new B);
print $a->p . "\n";
