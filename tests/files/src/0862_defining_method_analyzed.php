<?php
// @phan-file-suppress PhanUnusedPublicNoOverrideMethodParameter

class A {
    /**
     * @param A $a
     */
    public function test($a, $b) {
        // Should only output `A` because the phpdoc type is specified
        '@phan-debug-var $a';
    }
    public function test_untyped($x, $y) {
        // Should output the empty union type, A, and C, because it was called with the last two types.
        '@phan-debug-var $x';
    }
}
class B extends A {
    public function doTest() {
        $this->test(new A(), null);
        $this->test(new C(), null);
        $this->test_untyped(new A(), null);
        $this->test_untyped(new C(), null);
    }
}

class C extends A {
}
