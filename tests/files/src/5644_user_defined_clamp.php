<?php

// A function named clamp() declared by the analyzed code keeps its declared return type:
// the type inference for the built-in clamp() of PHP 8.6 does not apply to it.
// (Declaring it is an error in PHP 8.6+, where the built-in function exists)

function clamp($v, $min, $max): string
{
    return "$v$min$max";
}

$x = clamp(1, 0, 10);
'@phan-debug-var $x';
echo count($x);
