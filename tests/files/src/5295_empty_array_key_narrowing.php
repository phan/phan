<?php

// Test case for issue #5295: Too strict type inference after !empty condition on array

// Main reproduction case from the issue
function test_issue_5295(array $arr) {
    foreach ($arr as $el) {
        if (!empty($el['x'])) {
            // empty block - just checking the condition
        }
        // This should NOT be narrowed to falsey types since we only checked $el['x'], not $el['fn']
        $fn = $el['fn'];
        $fn();  // Should not emit any warnings about invalid callable
    }
}

// Test with multiple keys checked
function test_multiple_keys(array $arr) {
    foreach ($arr as $el) {
        if (!empty($el['x'])) {
            // no-op
        }
        if (!empty($el['y'])) {
            // no-op
        }
        // Accessing a different key should not be narrowed
        $z = $el['z'];
        $z->someMethod();
    }
}

// Test that narrowing still works for the same key
function test_same_key_narrowing(array $arr) {
    foreach ($arr as $el) {
        if (!empty($el['x'])) {
            // In this block, $el['x'] should be narrowed to non-empty/truthy
            $x = $el['x'];
            $x->method();  // OK - should have truthy types
        } else {
            // In this block, $el['x'] should be narrowed to falsey
            $x = $el['x'];
            // Can't call method on falsey value
        }
    }
}

// Test with nested arrays
function test_nested_array(array $data) {
    foreach ($data as $item) {
        if (!empty($item['nested']['key'])) {
            // no-op
        }
        // Accessing different nested key should not be affected
        $val = $item['nested']['other'];
        // $val should be mixed, not falsey
    }
}

// Test with literal key
function test_literal_key(array $arr) {
    foreach ($arr as $el) {
        if (!empty($el['config'])) {
            // no-op
        }
        // Different key should not be narrowed
        $fn = $el['fn'];
        $fn();
    }
}
