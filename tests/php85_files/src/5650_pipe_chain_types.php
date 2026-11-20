<?php

// The type of each stage of a chain of pipes is the type of that call:
// the types of the calls that Phan synthesizes for the pipes must not be mixed up.

function pipe_chain_5650(int $i): void
{
    $s = $i |> \abs(...) |> \strval(...) |> \strlen(...) |> \strval(...);
    '@phan-debug-var $s';
    $list = $i |> \strval(...) |> \strlen(...) |> \strval(...) |> \str_split(...);
    '@phan-debug-var $list';
    echo \count($s), \strlen($list);
}
pipe_chain_5650(1);
