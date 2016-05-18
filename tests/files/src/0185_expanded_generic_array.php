<?php

interface I1 {}

class A1 implements I1{}

class B1 implements I1 {}

class C1 {
    /** @var I1[] */
    public $is;
}

$c = new C1;
$c->is = [new A1]; // line 15
$c->is = [new A1, new A1]; // line 16
$c->is = [new A1, new B1]; // line 17
