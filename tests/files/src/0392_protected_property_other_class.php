<?php

// Test two classes that have a common base class attempting to access private/protected properties not on that base class.

class Base392 {
}

class A392 extends Base392{
    protected $prop1;
    private $prop2;
    public $prop3;
}

class B392 extends Base392 {
    public function test() {
        // Test phan's visibility checks for access and modification of properties
        $b = new A392();
        echo $b->prop1;
        echo $b->prop2;
        echo $b->prop3;  // valid
        $b->prop1 = 'x';
        $b->prop2 = 'y';
        $b->prop3 = 'z';  // valid
    }
}
(new B392())->test();
