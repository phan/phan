<?php

function test_and(int|string $x, bool $flag): void
{
    if (is_safe($x) && $flag) {
        '@phan-debug-var $x';
    }
    if ($flag && !is_safe($x)) {
        '@phan-debug-var $x';
    }
}

function test_or(int|string $x, bool $flag): void
{
    if ($flag || is_safe($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
    if (!is_safe($x) || $flag) {
        return;
    }
    '@phan-debug-var $x';
}

function test_ternary(int|string $x): void
{
    $a = is_safe($x) ? $x : null;
    $b = is_safe($x) ? null : $x;
    '@phan-debug-var $a, $b';
}

function test_assert(int|string $x): void
{
    assert(is_safe($x));
    '@phan-debug-var $x';
}

function test_comparisons(int|string $x): void
{
    if (is_safe($x) === true) {
        '@phan-debug-var $x';
    }
    if (is_safe($x) !== false) {
        '@phan-debug-var $x';
    }
    if (is_safe($x) == false) {
        '@phan-debug-var $x';
    }
}
