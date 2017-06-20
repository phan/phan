<?php

class Foo292 implements Serializable {
    public function serialize() { return ''; }
}

abstract class A292 {
    public abstract function f1(int $x);
    public abstract function f2(int $x);
}

// Should warn, did not implement f2
class B292 extends A292 {
    public function f1(int $x) {
    }
}

// This is abstract, should not warn
abstract class C292 extends A292 {
    public function f2(int $x) { }
}

// Should not warn, implements f1 and f2
class D292 extends C292 {
    public function f1(int $x) { }
}

trait ETrait292 {
    public function f1(int $x) { }
}

// should not warn, ETrait292 provides f1
class E292 extends C292 {
    use ETrait292;
}

interface Interface292 {
    function myFunction();
    function myImplementedFunction();
}

class ClassExtendingInterface292 implements Interface292 {
    function myImplementedFunction() {}
}
