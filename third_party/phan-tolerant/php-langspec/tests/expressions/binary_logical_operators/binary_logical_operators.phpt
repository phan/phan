--TEST--
PHP Spec test generated from ./expressions/binary_logical_operators/binary_logical_operators.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$month = 6;
if ($month > 1 && $month <= 12)
	echo "\$month $month is in-bounds\n";
else
	echo "\$month $month is out-of-bounds\n";

$month = 14;
if ($month > 1 && $month <= 12)
	echo "\$month $month is in-bounds\n";
else
	echo "\$month $month is out-of-bounds\n";

$month = 6;
if ($month < 1 || $month > 12)
	echo "\$month $month is out-of-bounds\n";
else
	echo "\$month $month is in-bounds\n";

$month = 14;
if ($month < 1 || $month > 12)
	echo "\$month $month is out-of-bounds\n";
else
	echo "\$month $month is in-bounds\n";

// sequence point

function f($a) { echo "inside f($a)\n"; return 10;}
function g($a) { echo "inside g($a)\n"; return 0;}

$i = 5;
$v = (f($i++) AND g($i));
var_dump($v);
$i = 0;
$v = (g($i++) OR f($i));
var_dump($v);
$i = 5;
$v = (f($i++) XOR g($i));
var_dump($v);
--EXPECT--
$month 6 is in-bounds
$month 14 is out-of-bounds
$month 6 is in-bounds
$month 14 is out-of-bounds
inside f(5)
inside g(6)
bool(false)
inside g(0)
inside f(1)
bool(true)
inside f(5)
inside g(6)
bool(true)
