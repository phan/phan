<?php
// Test for issue #4796: Class constant narrowing after null check
//
// NOTE: This test has limited effectiveness because Phan doesn't fully support
// PHP 8.3 typed class constants yet (see tests/php83_files/src/001_typed_class_constant.php).
// When typed constants are properly supported, this test should be updated.

class Foo {
    // Workaround: Use a constant with a value that could be overridden
    // We test the narrowing mechanism itself, even if type inference is limited
    public const NULLABLE_CONST = null;

    public function testNarrowing() {
        // The narrowing mechanism should store the narrowed type
        if (static::NULLABLE_CONST !== null) {
            // After narrowing, the constant's overridden type should be retrieved
            // Currently this may not work perfectly due to typed constant limitations
            $x = static::NULLABLE_CONST;
        }

        // Test with a property for comparison (properties DO support narrowing)
        if (self::$nullableProp !== null) {
            echo strlen(self::$nullableProp); // Should not warn after narrowing
        }
    }

    protected static ?string $nullableProp = null;
}

class Bar extends Foo {
    public const NULLABLE_CONST = 'value';
}
