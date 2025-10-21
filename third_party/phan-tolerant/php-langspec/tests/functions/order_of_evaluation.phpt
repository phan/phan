--TEST--
PHP Spec test generated from ./functions/order_of_evaluation.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// check order of evaluation of arguments in a function call w.r.t side effects

function f($p1, $p2, $p3, $p4, $p5)
{
	echo "f: \$p1 = $p1, \$p2 = $p2, \$p3 = $p3, \$p4 = $p4, \$p5 = $p5\n";
}

$i = 0;
f($i, ++$i, $i, $i = 12, --$i);		// arguments are evaluated L->R
// f: $p1 = 0, $p2 = 1, $p3 = 1, $p4 = 12, $p5 = 11

function g($p1, $p2, $p3, $p4, $p5)
{
	echo "g: \$p1 = $p1, \$p2 = $p2, \$p3 = $p3, \$p4 = $p4, \$p5 = $p5\n";
}

function h($p1, $p2, $p3, $p4, $p5)
{
	echo "h: \$p1 = $p1, \$p2 = $p2, \$p3 = $p3, \$p4 = $p4, \$p5 = $p5\n";
}

// Create a table of function designators

$funcTable = array(f, g, h);	// list of 3 functions
var_dump($funcTable);			// array of 3 strings
var_dump($funcTable[0]);		// a string

// Call all 3 functions indirectly through table

$funcTable[0](1,2,3,4,5);
$funcTable[1](10,20,30,40,50);
$funcTable[2](100,200,300,400,500);

// Put a side effect in the function designator and see the order of evaluation of
// that compared with the argument list expressions.

$i = 1;
$funcTable[$i++]($i, ++$i, $i, $i = 12, --$i);	// function designator side effect done first
// g: $p1 = 2, $p2 = 3, $p3 = 3, $p4 = 12, $p5 = 11
--EXPECTF--
f: $p1 = 0, $p2 = 1, $p3 = 1, $p4 = 12, $p5 = 11

Notice: Use of undefined constant f - assumed 'f' in %s/functions/order_of_evaluation.php on line 34

Notice: Use of undefined constant g - assumed 'g' in %s/functions/order_of_evaluation.php on line 34

Notice: Use of undefined constant h - assumed 'h' in %s/functions/order_of_evaluation.php on line 34
array(3) {
  [0]=>
  string(1) "f"
  [1]=>
  string(1) "g"
  [2]=>
  string(1) "h"
}
string(1) "f"
f: $p1 = 1, $p2 = 2, $p3 = 3, $p4 = 4, $p5 = 5
g: $p1 = 10, $p2 = 20, $p3 = 30, $p4 = 40, $p5 = 50
h: $p1 = 100, $p2 = 200, $p3 = 300, $p4 = 400, $p5 = 500
g: $p1 = 2, $p2 = 3, $p3 = 3, $p4 = 12, $p5 = 11
