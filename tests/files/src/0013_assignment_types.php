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
}

class B {
    private $a;

    public function __construct() {
        $str = 'string';

        $a = new A;
        $str = $a->str();
        $this->a = $a->instance();
    }
}
