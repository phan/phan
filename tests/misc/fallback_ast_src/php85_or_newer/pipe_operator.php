<?php

// Basic pipe usage
$length = "Hello World" |> strlen(...);

// Pipe into a user-defined function
function double(int $value): int
{
    return $value * 2;
}
$computed = 5
    |> double(...)
    |> double(...);

// Pipe into a helper using closures
$numbers = [1, 2, 3];
$sum = pipe_apply($numbers, fn(array $values): int => array_sum($values));

function pipe_apply(array $value, callable $fn): int
{
    return $fn($value);
}
