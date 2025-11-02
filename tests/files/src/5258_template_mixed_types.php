<?php

/**
 * Test that mixed types are allowed to satisfy template constraints (issue #5274).
 * This ensures consistency with how regular typed parameters handle mixed types.
 */

class TestClass {}

/**
 * @template T of TestClass
 * @param class-string<T> $class
 * @return T
 */
function getObjectByClass(string $class) {
    return new $class;
}

/**
 * Regular function with typed parameter for comparison
 */
function takesObject(TestClass $class): int {
    return 42;
}

// Should NOT warn - mixed could be compatible with template constraint
// Previously would emit: PhanTemplateTypeConstraintViolation
$obj1 = getObjectByClass($GLOBALS['x']);

// Should NOT warn - for consistency with template function above
$result1 = takesObject($GLOBALS['x']);

// Test with explicit mixed annotation
/** @var mixed $mixed */
$mixed = getMixed();

// Should NOT warn - mixed satisfies template constraint
$obj2 = getObjectByClass($mixed);

// Should NOT warn - mixed satisfies regular type
$result2 = takesObject($mixed);

/**
 * Test with multiple mixed sources
 */
function testVariousMixedSources(): void {
    // Array access of mixed type
    $arr = getArray();
    $obj3 = getObjectByClass($arr['key']);

    // Function return of mixed type
    $obj4 = getObjectByClass(getMixedValue());
}

/**
 * @template T of TestClass
 * @param T $instance
 * @return T
 */
function identity($instance) {
    return $instance;
}

// Should NOT warn - mixed satisfies constraint
$obj6 = identity($GLOBALS['y']);

/**
 * @return mixed
 */
function getMixed() {
    return null;
}

/**
 * @return array
 */
function getArray(): array {
    return [];
}

/**
 * @return mixed
 */
function getMixedValue() {
    return null;
}
