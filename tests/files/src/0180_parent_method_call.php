<?php

class A {}

class B {
    public function __construct(A $var) {}
}

class C extends B {
    public function __construct() {
        parent::__construct(null);
    }
}

$x = new B(null);
$y = new C();
