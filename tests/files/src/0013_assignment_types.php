<?php

class A {
    // @var int
    const ALPHA = 42;

    public function str() : string {
        return 'string';
    }

    public function instance() : A {
        return $this;
    }

    /** @return void */
    public function nothing() {
    }

    public function unannotatedNothing() {
    }
}

class B {
    private $a;
    private $b;

    public function __construct() {
        $str = 'string';

        $a = new A;
        $str = $a->str();
        $this->a = $a->instance();

        $str = $a->nothing();
        $str = $a->unannotatedNothing();
        $this->b = $a->nothing();
    }
}
