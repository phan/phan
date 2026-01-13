<?php

/**
 * Test that count() assertions narrow array shape types by making optional fields required.
 * @see https://github.com/phan/phan/issues/5406
 *
 * NOTE: This only works for "closed" array shapes where the real type is also an ArrayShapeType.
 * PHPDoc-annotated array shapes have a generic real type (like `array`) and are "open" -
 * they may have additional keys not declared in the shape, so count assertions cannot prove
 * which specific keys are present.
 */

/**
 * Test that the original issue example works - inferred types from code are "closed"
 */
function test_original_issue_example(): void {
    if (rand() % 2) {
        $a = ['a', 'b'];
    } else {
        $a = [];
    }
    '@phan-debug-var $a';

    if (count($a) == 2) {
        '@phan-debug-var $a';
        echo $a[0], $a[1];  // Should not warn - closed shape, count proves both keys exist
    }
}

/**
 * Test with === operator
 */
function test_identical_operator(): void {
    if (rand() % 2) {
        $a = ['x' => 1, 'y' => 2];
    } else {
        $a = [];
    }
    '@phan-debug-var $a';

    if (count($a) === 2) {
        '@phan-debug-var $a';
        echo $a['x'] + $a['y'];  // Should not warn
    }
}

/**
 * Test with mixed required and optional fields
 */
function test_mixed_required_optional(): void {
    if (rand() % 3 === 0) {
        $a = ['required' => 1];
    } elseif (rand() % 3 === 1) {
        $a = ['required' => 1, 'optional' => 'value'];
    } else {
        $a = ['required' => 1, 'optional' => 'other'];
    }
    '@phan-debug-var $a';

    if (count($a) === 2) {
        '@phan-debug-var $a';
        echo strlen($a['optional']);  // Should not warn - count proves 'optional' exists
    }
}
