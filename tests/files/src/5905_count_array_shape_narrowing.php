<?php

/**
 * Test that count() assertions narrow array shape types by making optional fields required.
 * @see https://github.com/phan/phan/issues/5406
 */

/**
 * @param array{0?:'a', 1?:'b'} $arr
 */
function test_count_equals_all_optional(array $arr): void {
    '@phan-debug-var $arr';
    if (count($arr) === 2) {
        '@phan-debug-var $arr';
        echo $arr[0];  // Should not warn - we know both keys exist
        echo $arr[1];  // Should not warn
    }
}

/**
 * @param array{a:int, b?:string} $arr
 */
function test_count_with_mixed_required_optional(array $arr): void {
    '@phan-debug-var $arr';
    if (count($arr) === 2) {
        '@phan-debug-var $arr';
        echo strlen($arr['b']);  // Should not warn - count proves 'b' exists
    }
}

/**
 * Test that == works the same as ===
 * @param array{0?:'a', 1?:'b'} $arr
 */
function test_count_equals_non_strict(array $arr): void {
    if (count($arr) == 2) {
        '@phan-debug-var $arr';
        echo $arr[0];  // Should not warn
        echo $arr[1];  // Should not warn
    }
}

/**
 * Test that the original issue example works
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
        echo $a[0], $a[1];  // Should not warn
    }
}
