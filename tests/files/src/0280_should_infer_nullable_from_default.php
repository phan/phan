<?php

class A280 {
    const NULLCONST = null;

    public function bar(?int $arg = null) { }
    public function bat(int $arg = 20) { }
    public function baz(?int $arg = 20) { }
    public function fee(int $arg = self::NULLCONST) { }
    public function foo(int $arg = null) { }
}

// No issues
class B280 extends A280{
    public function bar(int $arg = null) { }
    public function foo(?int $arg = null) { }
}

// No issues, but foo()
class C280 extends A280 {
    public function bat(int $arg = null) { } // fine
    public function fee(int $arg = 2) { }
    public function foo(int $arg = 20) {}  // invalid, it used to be nullable.
}

class D280 {
    public function mismatch(?int $x = 2.0) {}
}
function test280() {
    $a = new A280();
    $a->foo(null);
    $a->fee(2);
    $c = new C280();
    $c->foo(null);
    $c->fee(2);  // Sadly, this line does throw.
}
test280();
