<?php

// Regression test for https://github.com/phan/phan/issues/5514

function test_5514(string $a) {
    if ($a === '') return null;
    '@phan-debug-var $a';
    if ($a === '0') return null;
    '@phan-debug-var $a';
    return $a;
}

function test_5514_truthy(string $a) {
    if ($a) {
        '@phan-debug-var $a';
    }
}

/**
 * @param non-empty-string $x
 */
function expect_non_empty_string_5514(string $x) {
}

/**
 * @param non-falsy-string $x
 */
function expect_non_falsy_string_5514(string $x) {
}

expect_non_empty_string_5514('');
expect_non_empty_string_5514('0');
expect_non_empty_string_5514('hello');

expect_non_falsy_string_5514('');
expect_non_falsy_string_5514('0');
expect_non_falsy_string_5514('hello');
