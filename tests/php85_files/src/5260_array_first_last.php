<?php

/**
 * Test that array_first() and array_last() preserve types correctly (PHP 8.5 functions).
 * These functions should:
 * - Preserve the array value type
 * - Return null for empty arrays
 * - Work with various array types
 */

/**
 * Test with string arrays
 */
function testStringArray(): void {
    $strings = ['apple', 'banana', 'cherry'];

    // Should infer string|null (first value)
    $first = array_first($strings);
    if ($first !== null) {
        strlen($first); // Should NOT warn - type is string
    }

    // Should infer string|null (last value)
    $last = array_last($strings);
    if ($last !== null) {
        strlen($last); // Should NOT warn - type is string
    }
}

/**
 * Test with int arrays
 */
function testIntArray(): void {
    $numbers = [1, 2, 3, 4, 5];

    // Should infer int|null
    $first = array_first($numbers);
    if ($first !== null) {
        echo $first * 2; // Should NOT warn - type is int
    }

    // Should infer int|null
    $last = array_last($numbers);
    if ($last !== null) {
        echo $last + 10; // Should NOT warn - type is int
    }
}

/**
 * Test with empty arrays
 */
function testEmptyArray(): void {
    $empty = [];

    // Should return null for empty array
    $first = array_first($empty);
    $last = array_last($empty);

    // These should be null
    if ($first === null) {
        echo "First is null";
    }
    if ($last === null) {
        echo "Last is null";
    }
}

/**
 * Test with object arrays
 */
class Person {
    public string $name;
    public function __construct(string $name) {
        $this->name = $name;
    }
}

function testObjectArray(): void {
    $people = [
        new Person('Alice'),
        new Person('Bob'),
        new Person('Charlie')
    ];

    // Should infer Person|null
    $first = array_first($people);
    if ($first !== null) {
        echo $first->name; // Should NOT warn - type is Person
    }

    // Should infer Person|null
    $last = array_last($people);
    if ($last !== null) {
        echo $last->name; // Should NOT warn - type is Person
    }
}

/**
 * Test with associative arrays
 */
function testAssociativeArray(): void {
    $data = [
        'key1' => 100,
        'key2' => 200,
        'key3' => 300
    ];

    // Should infer int|null
    $first = array_first($data);
    if ($first !== null) {
        echo $first + 50; // Should NOT warn - type is int
    }

    // Should infer int|null
    $last = array_last($data);
    if ($last !== null) {
        echo $last - 50; // Should NOT warn - type is int
    }
}

/**
 * Test with mixed types
 */
function testMixedArray(): void {
    /** @var array<string|int> */
    $mixed = ['text', 123, 'more text', 456];

    // Should infer string|int|null
    $first = array_first($mixed);
    echo "First: $first";

    // Should infer string|int|null
    $last = array_last($mixed);
    echo "Last: $last";
}

/**
 * Test that null is properly handled
 */
function testNullHandling(): void {
    $strings = ['a', 'b', 'c'];

    // With proper null check
    $first = array_first($strings);
    if ($first !== null) {
        strlen($first); // Type is string here
    }

    $last = array_last($strings);
    if ($last !== null) {
        strlen($last); // Type is string here
    }
}

/**
 * Test with array containing null values
 */
function testArrayWithNulls(): void {
    $data = [null, 'valid', null];

    // Should infer string|null (null from array or from empty)
    $first = array_first($data);
    echo "First: $first";

    $last = array_last($data);
    echo "Last: $last";
}

/**
 * Test return value usage
 */
function testReturnValues(): string|null {
    $options = ['option1', 'option2', 'option3'];

    // Can return the result directly
    return array_first($options);
}

/**
 * Test with literal arrays
 */
function testLiteralArrays(): void {
    // Test inline array
    $first = array_first([10, 20, 30]);
    if ($first !== null) {
        echo $first * 2; // Should NOT warn - type is int
    }

    $last = array_last(['x', 'y', 'z']);
    if ($last !== null) {
        strlen($last); // Should NOT warn - type is string
    }
}
