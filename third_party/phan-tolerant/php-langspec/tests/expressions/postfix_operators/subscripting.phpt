--TEST--
PHP Spec test generated from ./expressions/postfix_operators/subscripting.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

///*
// See arrays.php for array creation and initialization
// This set of tests deals only with array-element access

$v = array(10, 20, 30);
var_dump($v);
echo $v[0]." ".$v["1"]." ".$v[2.654]."\n";	// access individual elements by int key

//echo 0[$v]."\n";	// see if [] operator is commutative (like C/C++). No it's not

foreach ($v as $k => $e)
{
	echo "key: ".$k.", value: ".$e."\n";
}

// try to access non-existant elements

echo "[7] contains >".($v[7] == NULL ? "NULL" : "??")
	."<, [12] contains >".($v[12] == NULL ? "NULL" : "??")."<\n";

$v[1] = 1.234;		// change the value (and type) of an existing element
$v[-10] = 19;		// insert a new element with int key -10
$v[5] = 54;			// insert a new element with int key 5
var_dump($v);

foreach ($v as $k => $e)
{
	echo "key: ".$k.", value: ".$e."\n";
}

$v["red"] = TRUE;	// insert a new element with string key "red"
$v[NULL] = 232;		// insert a new element with string key ""
var_dump($v);

foreach ($v as $k => $e)
{
	echo "key: ".$k.", value: ".$e."\n";
}

echo $v["red"]." ".$v[NULL]." ".$v[""]."\n"; // access individual elements by string key

$v = array(array(2,4,6,8), array(5,10), array(100,200,300));
var_dump($v);

foreach ($v as $k => $e)
{
	echo "outer key: ".$k.", outer value: ".$e."\n";
	foreach ($e as $k2 => $e2)
	{
		echo "inner key: ".$k2.", inner value: ".$e2."\n";
	}
}

echo "[0]: ".$v[0]."\n"; //
echo "[0][2]: ".$v[0][2]."\n"; // 6
echo "[1][1]: ".$v[1][1]."\n"; // 10

// show that associativity of () and [] is left-to-right

$z = array(array(2,4,6,8), array(5,10), array(100,200,300))[0][2];
var_dump($z);

$z = [array(2,4,6,8), array(5,10), array(100,200,300)][0][2];
var_dump($z);

$z = [[2,4,6,8], [5,10], [100,200,300]][0][2];	// acceses element with value 6
var_dump($z);

var_dump(["black", "white", "yellow"][1]);		// white
var_dump(["black", "white", "yellow"][1][2]);	// 1st [] is for array, 2nd for string
//*/

function f()
{
	return [1000, 2000, 3000];
}
//*/
///*
var_dump(f()[2]);	// acceses element with value 3000
//*/

///*
// checkout order of evaluation

$z = [[2,4,6,8], [5,10], [100,200,300]];
$i = 0;
var_dump($z[$i++][$i]);		// accesses [0][1] L->R int(4)
$i = 0;
var_dump($z[$i][$i++]);		// accesses [1][0] R->L int(5)
$i = 0;
var_dump($z[++$i][$i]);		// accesses [1][1] L->R int(10)
$i = 0;
var_dump($z[$i][++$i]);		// accesses [1][1] R->L int(10)
//*/

///*
// subscript some scalars

$z = 10;
var_dump($z);
$v = $z[12];		// results in NULL
var_dump($v);
$v = $z["red"];		// results in NULL
var_dump($v);

$z = [[2,4,6,8], [5,10], [100,200,300]];
var_dump($z[0][2]);			// results in 6
var_dump($z[0][2][3]);		// results in NULL

var_dump(f()[2]);			// results in 3000
var_dump(f()[2][1]);		// results in NULL

// 10[1];			// syntax error
$v = 10;
var_dump($v[1]);	// OK, results in NULL

// 1.23[1];			// syntax error
$v = 1.23;
var_dump($v[1]);	// OK, results in NULL

// TRUE[1];			// syntax error
$v = TRUE;
var_dump($v[1]);	// OK, results in NULL

// NULL[1];			// syntax error
$v = NULL;
var_dump($v[1]);	// OK, results in NULL

// subscript some strings

"red"[1];
var_dump("red"[1]);		// OK, results in "e"
var_dump("red"[1.9]);	// OK, results in "e"
var_dump("red"[-1]);	// OK, results in ""
var_dump("red"[10]);	// OK, results in ""
var_dump("red"["abc"]);	// Warning, results in "r" from [0]

// as string[xxx] results in a string, can keep applying more [], indefinitely

var_dump("red"[0]);				// OK, results in "r"
var_dump("red"[0][0]);			// OK, results in "r"
var_dump("red"[0][0][0]);		// OK, results in "r"
var_dump("red"[0][0][0][0]);	// OK, results in "r"

// change a string

$s = "red";
var_dump($s);
$s[1] = "X";		// OK; "e" -> "X"
var_dump($s);
$s[-5] = "Y";		// warning; string unchanged
var_dump($s);
$s[5] = "Z";		// extends string, padding with spaces
var_dump($s);

echo ">".$s[2]."<\n";
echo ($s[2] == " ") ? "[2] is a space\n" : "[2] is not a space\n";
echo ">".$s[3]."<\n";
echo ($s[3] == " ") ? "[3] is a space\n" : "[3] is not a space\n";
echo ">".$s[4]."<\n";
echo ($s[4] == " ") ? "[4] is a space\n" : "[4] is not a space\n";
echo ">".$s[5]."<\n";
echo ($s[5] == " ") ? "[5] is a space\n" : "[5] is not a space\n";

$s[0] = "DEF";		// "r" -> "D"; only 1 char changed
var_dump($s);
$s[0] = "MN";		// "D" -> "M"; only 1 char changed
var_dump($s);
$s[0] = "";			// "M" -> "\0"
var_dump($s);
$s["zz"] = "Q";		// warning; "Q" goes into [0]
var_dump($s);

// Is a string really a collection over which one can iterate? No.

//$s = "red";
//foreach ($s as $k => $e)
//{
	//echo "key: ".$k.", value: ".$e."\n";
//}

//*/

echo "--------------------\n";

//$v = array();
$v[] = 10;				// inserts using a key of the next available int value
$v["XX"] = 3;
$v[5] = 99;
$v[] = -2.3;
$v["AA"] = 234;
$v[12] = 100;
$v[] = 'red';
var_dump($v);
// var_dump($v[]);		// invalid; [] only allowed as a modifiable lvalue

// check that deprecated {} for subscripting works

$colors = array("red", "white");
var_dump($colors);

var_dump($colors[0]);
var_dump($colors { 0 } );

$colors { 1 } = 123;
var_dump($colors);

++$colors{1};
var_dump($colors);

$strs = [[10, 20], ["abc", "xyz"]];
var_dump($strs);
$strs[0][0] = 1.1;
$strs[0]{1} = 2.2;
$strs{1}[0] = 3.3;
$strs{1}{1} = 4.4;
var_dump($strs);
--EXPECTF--
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
10 20 30
key: 0, value: 10
key: 1, value: 20
key: 2, value: 30

Notice: Undefined offset: 7 in %s/expressions/postfix_operators/subscripting.php on line 28

Notice: Undefined offset: 12 in %s/expressions/postfix_operators/subscripting.php on line 29
[7] contains >NULL<, [12] contains >NULL<
array(5) {
  [0]=>
  int(10)
  [1]=>
  float(1.234)
  [2]=>
  int(30)
  [-10]=>
  int(19)
  [5]=>
  int(54)
}
key: 0, value: 10
key: 1, value: 1.234
key: 2, value: 30
key: -10, value: 19
key: 5, value: 54
array(7) {
  [0]=>
  int(10)
  [1]=>
  float(1.234)
  [2]=>
  int(30)
  [-10]=>
  int(19)
  [5]=>
  int(54)
  ["red"]=>
  bool(true)
  [""]=>
  int(232)
}
key: 0, value: 10
key: 1, value: 1.234
key: 2, value: 30
key: -10, value: 19
key: 5, value: 54
key: red, value: 1
key: , value: 232
1 232 232
array(3) {
  [0]=>
  array(4) {
    [0]=>
    int(2)
    [1]=>
    int(4)
    [2]=>
    int(6)
    [3]=>
    int(8)
  }
  [1]=>
  array(2) {
    [0]=>
    int(5)
    [1]=>
    int(10)
  }
  [2]=>
  array(3) {
    [0]=>
    int(100)
    [1]=>
    int(200)
    [2]=>
    int(300)
  }
}

Notice: Array to string conversion in %s/expressions/postfix_operators/subscripting.php on line 57
outer key: 0, outer value: Array
inner key: 0, inner value: 2
inner key: 1, inner value: 4
inner key: 2, inner value: 6
inner key: 3, inner value: 8

Notice: Array to string conversion in %s/expressions/postfix_operators/subscripting.php on line 57
outer key: 1, outer value: Array
inner key: 0, inner value: 5
inner key: 1, inner value: 10

Notice: Array to string conversion in %s/expressions/postfix_operators/subscripting.php on line 57
outer key: 2, outer value: Array
inner key: 0, inner value: 100
inner key: 1, inner value: 200
inner key: 2, inner value: 300

Notice: Array to string conversion in %s/expressions/postfix_operators/subscripting.php on line 64
[0]: Array
[0][2]: 6
[1][1]: 10
int(6)
int(6)
int(6)
string(5) "white"
string(1) "i"
int(3000)
int(4)
int(5)
int(10)
int(10)
int(10)
NULL
NULL
int(6)
NULL
int(3000)
NULL
NULL
NULL
NULL
NULL
string(1) "e"

Notice: String offset cast occurred in %s/expressions/postfix_operators/subscripting.php on line 143
string(1) "e"

Notice: Uninitialized string offset: -1 in %s/expressions/postfix_operators/subscripting.php on line 144
string(0) ""

Notice: Uninitialized string offset: 10 in %s/expressions/postfix_operators/subscripting.php on line 145
string(0) ""

Warning: Illegal string offset 'abc' in %s/expressions/postfix_operators/subscripting.php on line 146
string(1) "r"
string(1) "r"
string(1) "r"
string(1) "r"
string(1) "r"
string(3) "red"
string(3) "rXd"

Warning: Illegal string offset:  -5 in %s/expressions/postfix_operators/subscripting.php on line 161
string(3) "rXd"
string(6) "rXd  Z"
>d<
[2] is not a space
> <
[3] is a space
> <
[4] is a space
>Z<
[5] is not a space
string(6) "DXd  Z"
string(6) "MXd  Z"
string(6) " Xd  Z"

Warning: Illegal string offset 'zz' in %s/expressions/postfix_operators/subscripting.php on line 181
string(6) "QXd  Z"
--------------------
array(7) {
  [0]=>
  int(10)
  ["XX"]=>
  int(3)
  [5]=>
  int(99)
  [6]=>
  float(-2.3)
  ["AA"]=>
  int(234)
  [12]=>
  int(100)
  [13]=>
  string(3) "red"
}
array(2) {
  [0]=>
  string(3) "red"
  [1]=>
  string(5) "white"
}
string(3) "red"
string(3) "red"
array(2) {
  [0]=>
  string(3) "red"
  [1]=>
  int(123)
}
array(2) {
  [0]=>
  string(3) "red"
  [1]=>
  int(124)
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    int(10)
    [1]=>
    int(20)
  }
  [1]=>
  array(2) {
    [0]=>
    string(3) "abc"
    [1]=>
    string(3) "xyz"
  }
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    float(1.1)
    [1]=>
    float(2.2)
  }
  [1]=>
  array(2) {
    [0]=>
    float(3.3)
    [1]=>
    float(4.4)
  }
}
