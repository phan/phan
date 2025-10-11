<?php declare(strict_types=1);

// Test basic array_chunk with default preserve_keys (false)
function test_default_behavior() {
    $input = [1, 2, 3, 4, 5];
    $result = array_chunk($input, 2);
    '@phan-debug-var $result';
    // Should be list<list<int>>
    return $result;
}

// Test array_chunk with preserve_keys = true
function test_preserve_keys_true() {
    $input = ['a' => 1, 'b' => 2, 'c' => 3];
    $result = array_chunk($input, 2, true);
    '@phan-debug-var $result';
    // Should be list<array<string,int>>
    return $result;
}

// Test array_chunk with preserve_keys = false explicitly
function test_preserve_keys_false() {
    $input = ['a' => 1, 'b' => 2, 'c' => 3];
    $result = array_chunk($input, 2, false);
    '@phan-debug-var $result';
    // Should be list<list<int>>
    return $result;
}

// Test array_chunk with dynamic preserve_keys (unknown at analysis time)
function test_dynamic_preserve_keys(bool $preserve) {
    $input = ['a' => 1, 'b' => 2, 'c' => 3];
    $result = array_chunk($input, 2, $preserve);
    '@phan-debug-var $result';
    // Should be list<list<int>|array<string,int>>
    return $result;
}

// Test with string array
function test_string_array() {
    $input = ['foo', 'bar', 'baz'];
    $result = array_chunk($input, 2);
    '@phan-debug-var $result';
    // Should be list<list<string>>
    return $result;
}

// Test with mixed type array
function test_mixed_array() {
    $input = [1, 'foo', true];
    $result = array_chunk($input, 2);
    '@phan-debug-var $result';
    // Should be list<list<int|string|true>>
    return $result;
}

// Test with array of objects
function test_object_array() {
    $input = [new stdClass(), new stdClass()];
    $result = array_chunk($input, 1);
    '@phan-debug-var $result';
    // Should be list<list<stdClass>>
    return $result;
}

// Test with associative array preserve_keys=true
function test_associative_preserve_keys() {
    /** @var array<string, int> $input */
    $input = ['x' => 10, 'y' => 20];
    $result = array_chunk($input, 1, true);
    '@phan-debug-var $result';
    // Should be list<array<string,int>>
    return $result;
}

// Test with list type
function test_list_type() {
    /** @var list<int> $input */
    $input = [1, 2, 3, 4];
    $result = array_chunk($input, 2);
    '@phan-debug-var $result';
    // Should be list<list<int>>
    return $result;
}

// Test with array shape
function test_array_shape() {
    $input = [
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25]
    ];
    $result = array_chunk($input, 1);
    '@phan-debug-var $result';
    // Should be list<list<array{name:string,age:int}>>
    return $result;
}

// Test with array shape and preserve_keys=true
function test_array_shape_preserve() {
    $input = [
        'user1' => ['name' => 'Alice', 'age' => 30],
        'user2' => ['name' => 'Bob', 'age' => 25]
    ];
    $result = array_chunk($input, 1, true);
    '@phan-debug-var $result';
    // Should be list<array<string,array{name:string,age:int}>>
    return $result;
}

// Test return type mismatch - expecting wrong type
/** @return list<int> */
function test_wrong_return_type() {
    $input = [1, 2, 3];
    return array_chunk($input, 2); // Should emit PhanTypeMismatchReturn
}

// Test with numeric keys preserved
function test_numeric_keys_preserved() {
    $input = [10 => 'a', 20 => 'b', 30 => 'c'];
    $result = array_chunk($input, 2, true);
    '@phan-debug-var $result';
    // Should be list<array<int,string>>
    return $result;
}

// Test accessing chunked array elements
function test_chunk_element_access() {
    $input = [1, 2, 3, 4, 5];
    $chunks = array_chunk($input, 2);
    $first_chunk = $chunks[0];
    '@phan-debug-var $first_chunk';
    // Should be list<int>
    $first_element = $first_chunk[0];
    '@phan-debug-var $first_element';
    // Should be int
    return $first_element;
}

// Test iterating over chunks
function test_iterate_chunks() {
    $input = ['a' => 1, 'b' => 2, 'c' => 3];
    $chunks = array_chunk($input, 2, true);
    // @phan-suppress-next-line PhanSideEffectFreeForeachBody
    foreach ($chunks as $chunk) {
        '@phan-debug-var $chunk';
        // Should be array<string,int>
        // @phan-suppress-next-line PhanSideEffectFreeForeachBody, PhanUnusedVariableValueOfForeachWithKey, PhanUnusedVariable
        foreach ($chunk as $key => $value) {
            '@phan-debug-var $key';
            '@phan-debug-var $value';
            // key should be string, value should be int
        }
    }
}

// Test with union type input
function test_union_input() {
    /** @var array<int>|array<string> $input */
    $input = random_int(0, 1) ? [1, 2] : ['a', 'b'];
    $result = array_chunk($input, 2);
    '@phan-debug-var $result';
    // Should be list<list<int|string>>
    return $result;
}

// Test type mismatch when expecting preserve_keys behavior
/** @return list<array<string,int>> */
function test_expect_preserved_but_default() {
    $input = ['a' => 1, 'b' => 2];
    return array_chunk($input, 2); // Should emit PhanTypeMismatchReturn (default doesn't preserve keys)
}

// Test type mismatch when expecting list but got associative
/** @return list<list<int>> */
function test_expect_list_but_preserved() {
    $input = ['a' => 1, 'b' => 2];
    return array_chunk($input, 2, true); // Should emit PhanTypeMismatchReturn (preserve_keys=true)
}
