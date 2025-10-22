<?php

// Basic pipe usage
$length = "Hello World" |> strlen(...);

// Chain multiple pipes (unary functions only)
$result = "  Text  "
    |> trim(...)
    |> strtoupper(...);

// Pipe into a user-defined function
function double(int $value): int
{
    return $value * 2;
}
$computed = 5
    |> double(...)
    |> double(...);
