<?php
class A {
    public $p = [
        B::C,
    ];
}
class B {
    const C = 42;
}
