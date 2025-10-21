--TEST--
PHP Spec test generated from ./expressions/conditional_operator/conditional.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// check for even integer values by inspecting the low-order bit

for ($i = -5; $i <= 5; ++$i)
	echo "$i is ".(($i & 1 == TRUE) ? "odd\n" : "even\n");

// some simple examples

$a = 10 ? 100 : "Hello";
var_dump($a);
$a = 0 ? 100 : "Hello";
var_dump($a);

// omit 2nd operand

$a = 10 ? : "Hello";
var_dump($a);
$a = 0 ? : "Hello";
var_dump($a);

// put a side effect in the 1st operand

$i = 5;
$a = $i++ ? : "red";
var_dump($a);
$i = 5;
$a = ++$i ? : "red";
var_dump($a);

$i = PHP_INT_MAX;
$a = $i++ ? : "red";
var_dump($a);
$i = PHP_INT_MAX;
$a = ++$i ? : "red";
var_dump($a);

// sequence point

function f($a) { echo "inside f($a)\n"; return 0;}

$i = 5;
$i++ ? f($i) : f(++$i);
$i = 0;
$i++ ? f($i) : f(++$i);

// Test all kinds of scalar values to see which are ints or can be implicitly converted

$scalarValueList = array(10, -100, 0, 1.234, 0.0, TRUE, FALSE, NULL, "123", 'xx', "");
foreach ($scalarValueList as $v)
{
	echo "\$v = $v, ";
	$a = $v ? 100 : "Hello";
	var_dump($a);
}

// check associativity -- NOT the same as C/C++

$a = TRUE ? -1 : TRUE ? 10 : 20;
var_dump($a);
$a = (TRUE ? -1 : TRUE) ? 10 : 20;
var_dump($a);
$a = TRUE ? -1 : (TRUE ? 10 : 20);
var_dump($a);
--EXPECT--
-5 is odd
-4 is even
-3 is odd
-2 is even
-1 is odd
0 is even
1 is odd
2 is even
3 is odd
4 is even
5 is odd
int(100)
string(5) "Hello"
int(10)
string(5) "Hello"
int(5)
int(6)
int(9223372036854775807)
float(9.2233720368548E+18)
inside f(6)
inside f(2)
$v = 10, int(100)
$v = -100, int(100)
$v = 0, string(5) "Hello"
$v = 1.234, int(100)
$v = 0, string(5) "Hello"
$v = 1, int(100)
$v = , string(5) "Hello"
$v = , string(5) "Hello"
$v = 123, int(100)
$v = xx, int(100)
$v = , string(5) "Hello"
int(10)
int(10)
int(-1)
