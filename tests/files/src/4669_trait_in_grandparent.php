<?php

// Test for issue #4669: Trait protected method usage in ancestor's ancestor isn't detected
// This test verifies that trait methods are properly tracked through multiple inheritance levels

trait T {
    protected function func() {
        return 1;
    }
}

abstract class A {
    abstract protected function func();

    public function pub() {
        return $this->func();
    }
}

abstract class B extends A {}

class C extends B {
    use T;
}

// Test case 2: Even deeper nesting
abstract class D extends C {}
abstract class E extends D {}

class F extends E {}

// If the fix works, there should be NO PhanUnreferencedProtectedMethod warnings
// because T::func() is called via A::pub()
