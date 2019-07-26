<?php
error_reporting(E_ALL);
interface I276 {

}

class B276 implements I276 {
    /** @return static */
    public function instance() {
        return new static();
    }
}

class A276 implements I276 {
    public function foo(A276 $arg) { }

    public function bar(I276 $arg) { }

    public function baz(B276 $arg) { }

    public function test() {
        $foo = new static();
        $this->foo($foo);
        $foo->foo($foo);
        $this->bar($foo);
        $foo->bar($foo);
        $this->baz($foo);  // should warn
        $foo->baz($foo);  // should warn
        $foo->foo($this);
        // and check other classes
        $b = B276::instance();  // Deprecated: Non-static method B276::instance() should not be called statically
        $this->foo($b);  // should warn
        $this->bar($b);
    }
}
(new A276())->test();
