<?php

// Test detection of implicit float-to-int conversion in modulo operator
// This is deprecated in PHP 8.1+: https://wiki.php.net/rfc/implicit-float-int-deprecate

// Literal float operands - should warn
$result1 = 5.5 % 2;
$result2 = 10 % 2.5;
$result3 = 7.7 % 3.3;

// Variable float operands - should warn
$float = 5.5;
$int = 2;
$result4 = $float % $int;
$result5 = $int % $float;

// Float that equals int - should still warn (it's still a float type)
$result6 = 5.0 % 2;

// Valid int operands - should NOT warn
$result7 = 10 % 3;
$int1 = 10;
$int2 = 3;
$result8 = $int1 % $int2;

// Function returning float - should warn
function getFloat(): float {
    return 5.5;
}

$result9 = getFloat() % 2;
$result10 = 10 % getFloat();

// Mixed types that could be float - should warn if type analysis determines it's float
/** @var int|float $mixed */
$mixed = 5.5;
$result11 = $mixed % 2;

// Union type with float
/** @var float|string $union */
$union = 5.5;
$result12 = $union % 2;
