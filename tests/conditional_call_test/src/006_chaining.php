<?php

function chained(int|string $x): bool
{
    return is_string($x);
}

function test_chaining(int|string $x): void
{
    // ConditionalCallTestPlugin makes $x a string, then ConditionalCallSecondTestPlugin sees string and makes it non-empty-string
    if (chained($x)) {
        '@phan-debug-var $x';
    }
}
