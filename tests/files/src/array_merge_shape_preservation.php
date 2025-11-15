<?php
// Test case 1: array_merge with shape and generic array
// Note: when merging with a generic array parameter, the generic array might
// have different types for the same keys, so we do get false|... type
function test_merge_shape_with_generic(array $params = []) {
    $params = array_merge([
        'shop_section' => null,
        'with_count' => false,
    ], $params);
    h($params['shop_section']);  // Should NOT warn - type is null (from first array, not overridden by generic)
    // Note: with_count could be overridden by $params to other types, so we don't test it
}

// Test case 2: array_merge with only shapes
function test_merge_two_shapes() {
    $result = array_merge(
        ['name' => 'John', 'age' => 30],
        ['email' => 'john@example.com']
    );
    // Result shape should have: name, age, email
    h($result['name']);     // Should NOT warn - type is string
    h($result['email']);    // Should NOT warn - type is string
}

// Test case 3: array_merge where later shape overrides earlier
function test_merge_overlapping_shapes() {
    $result = array_merge(
        ['status' => 'active', 'count' => 10],
        ['status' => null]  // This overrides the earlier 'active' with null
    );
    h_null($result['status']); // Should NOT warn - type is null
}

// Test case 4: Original example that triggered the bug
function f(array $params = []) {
    $params = array_merge([
        'shop_section' => null,
        'with_count' => false,
    ], $params);
    h($params['shop_section']);
}

function g() {
    $params = [
        'shop_section' => null,
        'with_count' => false,
    ];
    h($params['shop_section']);
}

// Test case 5: Mixed first argument with pure last shape (Etsyweb case)
// First arg is generic array (from array_map), last arg is pure shape
function test_mixed_with_pure_last() {
    $encoded = array_map('json_encode', [
        'summary_history' => [],
        'user_input' => 'hello',
    ]);
    $result = array_merge($encoded, ['role' => 'user']);
    // The last argument is pure shape, so 'role' key is guaranteed
    h($result['role']);  // Should NOT warn - type is string literal 'user'
}

// Test case 6: Shape union as parameter merged with pure shape
/**
 * @param array{a:int}|array{b:string} $mixed
 */
function test_param_union_with_pure_last($mixed) {
    $result = array_merge($mixed, ['status' => 'ok']);
    // Last argument is pure shape, so 'status' key is guaranteed
    h($result['status']);  // Should NOT warn - type is string literal
}

// Test case 7: First argument guarantees a key while later arguments may or may not override it
/**
 * @param array{x?:string} $maybe
 */
function test_first_argument_shape_is_preserved(array $maybe) {
    $result = array_merge(['x' => 'foo'], $maybe);
    h($result['x']);  // Should NOT warn - key 'x' is guaranteed present
}

// Helper functions
function h(?string $arg) {
    echo $arg;
}

function h_null(?string $arg) {
    echo $arg;
}

f();
g();
test_merge_shape_with_generic();
test_merge_two_shapes();
test_merge_overlapping_shapes();
test_mixed_with_pure_last();
test_param_union_with_pure_last(['a' => 1]);
test_first_argument_shape_is_preserved([]);
