<?php

// Test for issue #4431: Intersection types with unknown classes should still analyze known types

interface KnownInterface {
    public function knownMethod(string $arg): void;
    public function anotherMethod(int $x): string;
}

// UnknownClass is not defined anywhere

/**
 * @param KnownInterface&UnknownClass $obj
 */
function test_method_call_with_intersection($obj): void {
    // Should NOT warn about undeclared method (KnownInterface defines it)
    // Should check argument types against KnownInterface::knownMethod
    $obj->knownMethod('valid string');

    // Should warn about wrong argument type (int instead of string)
    $obj->knownMethod(123);

    // Should work with method that returns a value
    $result = $obj->anotherMethod(42);
    '@phan-debug-var $result';  // Should infer string

    // Should warn about wrong argument type
    $obj->anotherMethod('not an int');
}

/**
 * @param KnownInterface&Traversable $both_known
 */
function test_both_known($both_known): void {
    // Both types known - should work normally
    $both_known->knownMethod('valid');

    // Should warn about type mismatch
    $both_known->knownMethod(456);
}

/**
 * @param UnknownClass1&UnknownClass2 $both_unknown
 */
function test_both_unknown($both_unknown): void {
    // Both unknown - should warn about undeclared method
    $both_unknown->someMethod();
}

/**
 * Test with multiple known interfaces
 * @param KnownInterface&Traversable&UnknownClass $multi
 */
function test_multiple_types($multi): void {
    // Should check against known types
    $multi->knownMethod('ok');
    $multi->knownMethod(789);  // Wrong type
}
