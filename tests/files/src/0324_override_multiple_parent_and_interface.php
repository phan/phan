<?php

interface MyInterface324 {
    public function foo(int $x) : int;
}

interface MyInterface324B {
    public function foo(string $x) : string;
}

interface MyInterface324C {
    public function foo(int $x) : string;
}

interface MyInterface324D {
    public function foo(int $x, int $y) : string;
}

class MyBase324 {
    public function foo(string $x) : string {
        return strlen($x);
    }
}

trait MyTrait324 {
    public abstract function foo(string $x) : int;
}

trait MyTrait324B {
    public abstract function foo(string $x) : array;
}

// The implementation inherited from MyBase324 should be type checked against that list of interfaces
class MySubclass extends MyBase324 implements MyInterface324, MyInterface324B, MyInterface324C, MyInterface324D {
    use MyTrait324;
    use MyTrait324;
    use MyTrait324B;
}
