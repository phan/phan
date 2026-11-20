<?php

function is_safe(int|string $x): bool
{
    return is_string($x);
}

function is_ready(): bool
{
    return true;
}

function not_registered(int|string $x): bool
{
    return is_string($x);
}

function test_if_else(int|string $x): void
{
    if (is_safe($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
    if (!is_safe($x)) {
        '@phan-debug-var $x';
    }
}

function test_early_return(int|string $x): void
{
    if (!is_safe($x)) {
        return;
    }
    '@phan-debug-var $x';
}

function test_fall_through(int|string $x): void
{
    if (is_safe($x)) {
        return;
    }
    '@phan-debug-var $x';
}

function test_not_registered(int|string $x): void
{
    // Unchanged: there is no closure for not_registered()
    if (not_registered($x)) {
        '@phan-debug-var $x';
    } else {
        '@phan-debug-var $x';
    }
}

function test_closure_without_arguments(): void
{
    if (is_ready()) {
        '@phan-debug-var $checked';
    }
    if (!is_ready()) {
        '@phan-debug-var $checked';
    }
}
