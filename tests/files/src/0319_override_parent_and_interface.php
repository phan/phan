<?php

interface MyInterface319 {
    public function foo(int $x) : int;
    public function baz(string $x) : array;
}

class MyBase319 {
    public final function foo(int $x) : int {
        return $x * 2;
    }
    public final function baz(string $x) : array {
        return [$x . 'suffix'];
    }
}

// The fact that there's an interface with a non-final method shouldn't cause an error.
// However, the fact that baz overrides the definition in the base class should.
class MySubclass extends MyBase319 implements MyInterface319 {
    public final function baz(string $x) : array {
        return ["prefix$x"];
    }
}
