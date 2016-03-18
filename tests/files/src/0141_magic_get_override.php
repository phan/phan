<?php

class A {
    /** @var B */
    private $b;

    public function __get($f) {
        return new A();
    }

    public function foo() {
        print 'hello world';
    }
}

class B {}

(new A)->b->foo();
