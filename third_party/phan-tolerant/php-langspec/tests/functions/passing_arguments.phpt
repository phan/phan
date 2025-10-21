--TEST--
PHP Spec test generated from ./functions/passing_arguments.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// a simple example of passing by value and by reference

function f($p1, &$p2)
{
	echo "f In:  \$p1: $p1, \$p2: $p2\n";
	$p1 = 100;		// only the local copy is changed
	$p2 = 200;		// actual argument's value changed
	echo "f Out: \$p1: $p1, \$p2: $p2\n";
}

///*
$a1 = 10;
$a2 = 20;
var_dump($a1);
var_dump($a2);
f($a1, $a2);		// variable $a2 is passed by reference
var_dump($a1);
var_dump($a2);

$a2 = [10,20,30];
var_dump($a2);
f($a1, $a2[1]);	// variable $a2[1] is passed by reference
var_dump($a1);
var_dump($a2);

class C
{
	public $member;
}
$o = new C;
$o->member = "Red";
var_dump($o);
f($a1, $o->member);		// variable $o->member is passed by reference
var_dump($o);
//*/

// passing by reference explored further

function g(&$p1)
{
	echo "g In:  \$p1: $p1\n";
	$p1 = 200;		// actual argument's value changed
	echo "g Out: \$p1: $p1\n";
}

///*
$a2 = 0;
// g(TRUE);				// PHP5 32/64, Error: Only variables can be passed by reference
						   // HHVM,       Error: Cannot pass parameter 1 by reference
   g($a2 = TRUE);		// Suspicious "behavior"
   						// PHP5 32, allowed quietly, but does not change the original argument
   						// PHP5 64  allowed with warning "PHP Strict standards:  Only variables
   						//     should be passed by reference", but does not change the original argument
						   // HHVM, allowed quietly, but does not change the original argument
var_dump($a2);
   $a2 = TRUE;
   g($a2);				// OK; passing a modifiable lvalue by reference
   var_dump($a2);

// following tests have different values for $a2, and give results like the case above

// g(-123);
   g($a2 = -123);
var_dump($a2);
   $a2 = -123;
   g($a2);
var_dump($a2);
// g(1.23e3);
   g($a2 = 1.23e3);
var_dump($a2);
   $a2 = 1.23e3;
   g($a2);
var_dump($a2);
// g(NULL);
   g($a2 = NULL);
var_dump($a2);
   $a2 = NULL;
   g($a2);
var_dump($a2);
// g("abc");
   g($a2 = "abc");
var_dump($a2);
   $a2 = "abc";
   g($a2);
var_dump($a2);
// g([1,2,3]);
   g($a2 = [1,2,3]);
var_dump($a2);
   $a2 = [1,2,3];
   g($a2);
var_dump($a2);
//*/

///*
// based on finding from above, further testing
$z = 10;
// ($z = 5) = 20;	   // All 3, Error: unexpected '='
// ($z = 5)++;       // All 3, Error: unexpected '++'
// ($z += 5) = 20;	// All 3, Error: unexpected '='
// ($z += 5)++;		// All 3, Error: unexpected '++'
//*/

///*
$z = 10;
g($z);				// create a reference to a modifiable lvalue
var_dump($z);
// g($z + 1);		// PHP5 32/64, Error: Only variables can be passed by reference
                  // HHVM,       Error: Cannot pass parameter 1 by reference
g($z -= 2);			// PHP5 32, allowed quietly, but does not change the original argument
   					// PHP5 64  allowed with warning "PHP Strict standards:  Only variables
   					//     should be passed by reference", but does not change the original argument
                  // HHVM, allowed quietly, but does not change the original argument
var_dump($z);
g($z = $z - 2);   // same as above
var_dump($z);
// g($z++);			// PHP5 32/64, Error: Only variables can be passed by reference
                  // HHVM,       Error: Cannot pass parameter 1 by reference
// ($z++)++;		// All 3, Error: unexpected '++'
g(--$z);          // PHP5 32, allowed quietly, but does not change the original argument
   					// PHP5 64  allowed with warning "PHP Strict standards:  Only variables
   					//     should be passed by reference", but does not change the original argument
                  // HHVM, allowed quietly, but does not change the original argument
// ----$z;			// All 3, Error: unexpected '--'
var_dump($z);
//*/

///*
function k() { return 10; }
var_dump(k());
g(k());        // PHP5 32, allowed quietly, but (presumably) does not change the original argument
					// PHP5 64  allowed with warning "PHP Strict standards:  Only variables
					//     should be passed by reference", but (presumably) does not change the original argument
// k() = 10;   // All 3, Error: Can't use function return value in write context
					// HHVM, allowed quietly, but (presumably) does not change the original argument
//*/
--EXPECTF--
int(10)
int(20)
f In:  $p1: 10, $p2: 20
f Out: $p1: 100, $p2: 200
int(10)
int(200)
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
f In:  $p1: 10, $p2: 20
f Out: $p1: 100, $p2: 200
int(10)
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(200)
  [2]=>
  int(30)
}
object(C)#1 (1) {
  ["member"]=>
  string(3) "Red"
}
f In:  $p1: 10, $p2: Red
f Out: $p1: 100, $p2: 200
object(C)#1 (1) {
  ["member"]=>
  int(200)
}

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 60
g In:  $p1: 1
g Out: $p1: 200
bool(true)
g In:  $p1: 1
g Out: $p1: 200
int(200)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 73
g In:  $p1: -123
g Out: $p1: 200
int(-123)
g In:  $p1: -123
g Out: $p1: 200
int(200)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 79
g In:  $p1: 1230
g Out: $p1: 200
float(1230)
g In:  $p1: 1230
g Out: $p1: 200
int(200)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 85
g In:  $p1: 
g Out: $p1: 200
NULL
g In:  $p1: 
g Out: $p1: 200
int(200)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 91
g In:  $p1: abc
g Out: $p1: 200
string(3) "abc"
g In:  $p1: abc
g Out: $p1: 200
int(200)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 97

Notice: Array to string conversion in %s/functions/passing_arguments.php on line 51
g In:  $p1: Array
g Out: $p1: 200
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}

Notice: Array to string conversion in %s/functions/passing_arguments.php on line 51
g In:  $p1: Array
g Out: $p1: 200
int(200)
g In:  $p1: 10
g Out: $p1: 200
int(200)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 119
g In:  $p1: 198
g Out: $p1: 200
int(198)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 124
g In:  $p1: 196
g Out: $p1: 200
int(196)

Notice: Only variables should be passed by reference in %s/functions/passing_arguments.php on line 129
g In:  $p1: 195
g Out: $p1: 200
int(195)
int(10)
%Ag In:  $p1: 10
g Out: $p1: 200
