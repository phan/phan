<?php

class A {

    /** @var string */
    public $p = B::C;

}

class B {
    const C = 'string';
}

