<?php

class A {
    protected $protected = 1;
    private $private = 'a';
}

class B extends A {
    public $protected = 2;
    public $private = 'b';
}

$v = new B;
$v->protected = 3;
$v->private = 'c';
