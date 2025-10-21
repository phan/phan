--TEST--
list() intrinsic
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

echo "--------- test with full and omitted LHS vars -------------\n";

$v = list($min, $max, $avg) = array(0, 100, 67);
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

$v = list($min, $max, $avg) = array(2 => 67, 1 => 100, 0 => 0);
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

list($min, , $avg) = array(0, 100, 67);
echo "\$min: $min, , \$avg: $avg\n";

list(, $max, $avg) = array(0, 100, 67);
echo ", \$max: $max, \$avg: $avg\n";

list(, , $avg) = array(0, 100, 67);
echo ", , \$avg: $avg\n";

list($min, $max, ) = array(0, 100, 67);
echo "\$min: $min, \$max: $max,\n";

list($min, $max) = array(0, 100, 67);
echo "\$min: $min, \$max: $max\n";

list($min, , ) = array(0, 100, 67);
echo "\$min: $min, ,\n";

list($min) = array(0, 100, 67);
echo "\$min: $min\n";

echo "--------- test with more array elements than variables -------------\n";

$v = list($min, $max, $avg) = array(0, 100, 67, 22, 33);
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

echo "--------- test with fewer array elements than variables -------------\n";

var_dump(isset($min));
var_dump($min);
var_dump(isset($max));
var_dump($max);
var_dump(isset($avg));
var_dump($avg);

$v = list($min, $max, $avg) = array(100, 500);	// Undefined offset: 2
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

var_dump(isset($min));	// TRUE
var_dump($min);
var_dump(isset($max));	// TRUE
var_dump($max);
var_dump(isset($avg));	// FALSE
var_dump($avg);

echo "--------- test with sufficient array elements but not consecutive keys -------------\n";

$v = list($min, $max, $avg) = array(0, 2 => 100, 4 => 67);
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

var_dump(isset($min));	// TRUE
var_dump($min);
var_dump(isset($max));	// FALSE
var_dump($max);
var_dump(isset($avg));	// TRUE
var_dump($avg);

echo "--------- test with NULL rather than array -------------\n";

//$v = list($min, $max, $avg);	// syntax error, unexpected ';', expecting '='

$v = list($min, $max, $avg) = NULL;
var_dump(isset($v));	// FALSE

echo "--------- test with mixed array -------------\n";

$v = list($min, $max, $avg) = [10, "a" => 20, 30, "b" => 40, 50];
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

echo "--------- test with non-numeric array -------------\n";

$v = list($min, $max, $avg) = ["x" => 10, "a" => 20, "y" => 30];
	// Undefined offset: 0, 1, 2
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

var_dump(isset($min));	// FALSE
var_dump(isset($max));	// FALSE
var_dump(isset($avg));	// FALSE

echo "--------- test with array element being an array -------------\n";

$v = list($min, $max, $avg) = array(0, array(100, 67));	// Undefined offset: 2
print_r($v);

var_dump(isset($min));	// TRUE
var_dump($min);
var_dump(isset($max));	// TRUE
var_dump($max);			// array(100, 67)
var_dump(isset($avg));	// FALSE
var_dump($avg);

echo "--------- test with nested lists -------------\n";

$v = list($min, list($max, $avg)) = [0, [1 => 67, 99, 0 => 100], 33];
echo "\$min: $min, \$max: $max, \$avg: $avg\n";
print_r($v);

echo "--------- test with target vars being array elements -------------\n";

$v = list($a[0], $a[2], $a[4]) = array(0, 100, 67);
print_r($a);
print_r($v);

// All of the following are invalid
/*echo "--------- test with no variables -------------\n";

$v = list() = array(0, 100, 67);
print_r($v);

$v = list(,) = array(0, 100, 67);
print_r($v);

$v = list(,,) = array(0, 100, 67);
print_r($v);*/
--EXPECTF--
--------- test with full and omitted LHS vars -------------
$min: 0, $max: 100, $avg: 67
Array
(
    [0] => 0
    [1] => 100
    [2] => 67
)
$min: 0, $max: 100, $avg: 67
Array
(
    [2] => 67
    [1] => 100
    [0] => 0
)
$min: 0, , $avg: 67
, $max: 100, $avg: 67
, , $avg: 67
$min: 0, $max: 100,
$min: 0, $max: 100
$min: 0, ,
$min: 0
--------- test with more array elements than variables -------------
$min: 0, $max: 100, $avg: 67
Array
(
    [0] => 0
    [1] => 100
    [2] => 67
    [3] => 22
    [4] => 33
)
--------- test with fewer array elements than variables -------------
bool(true)
int(0)
bool(true)
int(100)
bool(true)
int(67)

Notice: Undefined %s: 2 in %s/expressions/primary_expressions/intrinsics_list.php on line 57
$min: 100, $max: 500, $avg: 
Array
(
    [0] => 100
    [1] => 500
)
bool(true)
int(100)
bool(true)
int(500)
bool(false)
NULL
--------- test with sufficient array elements but not consecutive keys -------------

Notice: Undefined %s: 1 in %s/expressions/primary_expressions/intrinsics_list.php on line 70
$min: 0, $max: , $avg: 100
Array
(
    [0] => 0
    [2] => 100
    [4] => 67
)
bool(true)
int(0)
bool(false)
NULL
bool(true)
int(100)
--------- test with NULL rather than array -------------
bool(false)
--------- test with mixed array -------------
$min: 10, $max: 30, $avg: 50
Array
(
    [0] => 10
    [a] => 20
    [1] => 30
    [b] => 40
    [2] => 50
)
--------- test with non-numeric array -------------

Notice: Undefined %s: 0 in %s/expressions/primary_expressions/intrinsics_list.php on line 96

Notice: Undefined %s: 1 in %s/expressions/primary_expressions/intrinsics_list.php on line 96

Notice: Undefined %s: 2 in %s/expressions/primary_expressions/intrinsics_list.php on line 96
$min: , $max: , $avg: 
Array
(
    [x] => 10
    [a] => 20
    [y] => 30
)
bool(false)
bool(false)
bool(false)
--------- test with array element being an array -------------

Notice: Undefined %s: 2 in %s/expressions/primary_expressions/intrinsics_list.php on line 107
Array
(
    [0] => 0
    [1] => Array
        (
            [0] => 100
            [1] => 67
        )

)
bool(true)
int(0)
bool(true)
array(2) {
  [0]=>
  int(100)
  [1]=>
  int(67)
}
bool(false)
NULL
--------- test with nested lists -------------
$min: 0, $max: 100, $avg: 67
Array
(
    [0] => 0
    [1] => Array
        (
            [1] => 67
            [2] => 99
            [0] => 100
        )

    [2] => 33
)
--------- test with target vars being array elements -------------
Array
(
    [0] => 0
    [2] => 100
    [4] => 67
)
Array
(
    [0] => 0
    [1] => 100
    [2] => 67
)
