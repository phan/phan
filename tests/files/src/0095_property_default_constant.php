<?php
class B extends A {
    public $p = parent::C;
    public $q = A::D;
}
class A {
    const C = 42;
    const D = 'string';
}
