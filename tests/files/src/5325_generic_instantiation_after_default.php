<?php
/**
 * Test that regular instantiations of generic classes still warn after analyzing
 * a default value instantiation of the same class.
 * This verifies the memoization cache key includes the is_default_value flag.
 * Issue #5324 / GitHub Review
 */

namespace Test\GenericInstantiationAfterDefault;

/** @template T */
class GenericClass {
    public function __construct() {}
}

class TestClass {
    // First: Analyze default value (with is_default_value = true)
    public function __construct(
        /**
         * @var GenericClass<int>
         */
        protected readonly GenericClass $default = new GenericClass(),
    ) {
    }

    // Second: Regular instantiation (with is_default_value = false)
    // This should still warn about missing template parameter,
    // not be suppressed by the memoization of the default value
    public function otherMethod() {
        $x = new GenericClass();  // Should warn about missing template T
    }
}
