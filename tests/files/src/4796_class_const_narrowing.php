<?php
// Test for issue #4796: Class constant narrowing after null check
//
// This test verifies that:
// 1. Class constants can be narrowed after type checks (e.g., `if (static::CONST !== null)`)
// 2. The narrowed type is correctly applied inside the conditional block
// 3. No false positive warnings are produced for valid narrowed accesses
//
// When run with `-n` (no config), this test produces no warnings because the narrowing works correctly.

class Foo {
    // Test case from the bug report: base class has null, child class overrides with int
    public const T = null;

    /** @var int */
    public $value = -1;

    public function testNarrowing() {
        // After the null check, static::T should be narrowed to exclude null
        // This should NOT produce a PhanTypeMismatchProperty warning
        if (static::T !== null) {
            $this->value = static::T;  // Should work - narrowed type from child classes
        }

        // Test that we still get a warning OUTSIDE the narrowing scope
        // This should warn because static::T could be null (from base class)
        $this->value = static::T;
    }

    // Test with a property for comparison (properties already supported narrowing)
    protected static ?string $nullableProp = null;

    public function testPropertyNarrowing() {
        if (self::$nullableProp !== null) {
            echo strlen(self::$nullableProp); // Should not warn after narrowing
        }
    }
}

class Bar extends Foo {
    // Child class overrides the constant with a non-null value
    public const T = 42;
}
