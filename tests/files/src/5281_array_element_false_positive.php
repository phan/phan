<?php

class OtherClass {}

// Test case: union of generic array and array shape
// Accessing non-existent key should return mixed, not extract types from shape
/** @param array $generic */
function test_union($generic) {
    $specific = ['class' => new OtherClass];
    $arr = rand() % 2 ? $generic : $specific;
    $callable = $arr['callable'] ?? null;
    if ($callable) {
        // Before fix: false positive - Phan thought $callable could be OtherClass
        // After fix: $callable is mixed, no false positive
        $callable();
    }
}

// Test case: pure array shape
// Accessing non-existent key should return null
function test_shape_only() {
    $arr = ['class' => new OtherClass];
    $callable = $arr['callable'] ?? null;
    if ($callable) {
        $callable();
    }
}
