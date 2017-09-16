<?php

namespace {
abstract class A355 {
    abstract function foo();
}

interface I355 {
    function functionOfI();
}

class B355 extends namespace\A355 implements namespace\I355 {
    public function foo() {}
    public function functionOfI() {}
}
}

namespace NS355 {

use B355;

abstract class A355 {
    abstract function fooInNamespace();
}

class D355 extends namespace\A355 {
    public function fooInNamespace() {}
    public function functionOfI() {}
}

// Phan should warn that the interface doesn't exist
class E355 implements namespace\I355 {
    /** @override */
    public function functionOfI() {}
}

// Should not warn
class F355 implements \I355 {
    /** @override */
    public function functionOfI() {}
}

$c = new D355();  // Should work

$d = new B355();  // Should work
$d = new namespace\B355();  // Should fail.

function accepts_B355(B355 $x) {
}

accepts_B355(new B355());  // should work
accepts_B355(new \B355());  // should work
accepts_B355(new namespace\B355());  // should fail
}
