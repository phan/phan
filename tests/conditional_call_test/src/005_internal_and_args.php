<?php

function named_args(int|string $first, int|string $second): bool
{
    return is_string($first) && is_string($second);
}

function test_internal_function(string $x): void
{
    // The closure for \in_array runs after Phan's own narrowing: it sees 'a'|'b' and records it in $seen
    if (in_array($x, ['a', 'b'], true)) {
        '@phan-debug-var $x, $seen';
    }
}

function test_first_class_callable(int|string $x): void
{
    // Unchanged: `is_ready(...)` creates a closure, it does not call is_ready()
    if (is_ready(...)) {
        '@phan-debug-var $checked';
    }
    if (is_safe(...)) {
        '@phan-debug-var $x';
    }
}

function test_named_arguments(int|string $x, int|string $y): void
{
    // The closure is passed the arguments in declaration order: [$x, $y]
    if (named_args(second: $y, first: $x)) {
        '@phan-debug-var $x, $y, $args_seen';
    }
}
