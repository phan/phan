--TEST--
PHP Spec test generated from ./expressions/postfix_operators/subscripting_2.php
--FILE--
<?php

echo "====== array without index; simple assignment =========\n";

$a = array(3 => 33, -1 => -11);
var_dump($a[] = 991);		// creates $a[4]
var_dump($a);
echo "------\n";

$a = array(-30 => 33, -10 => -11);
var_dump($a[] = 991);		// creates $a[0]
var_dump($a);
echo "------\n";

$a = array(0 => 33, -10 => 11);
var_dump($a[] = 991);		// creates $a[1]
var_dump($a);
echo "------\n";

$a = array('a' => 33, 'x' => -11);
var_dump($a[] = 991);		// creates $a[0]
var_dump($a);

echo "====== array without index; compound assignment =========\n";

$a = array('a' => 33, 'x' => -11);
var_dump($a[] += 991);		// creates $a[0]
var_dump($a);
echo "------\n";

$a = array('a' => 33, 'x' => -11);
var_dump($a[] -= 991);		// creates $a[0]
var_dump($a);
echo "------\n";

$a = array('a' => 33, 'x' => -11);
var_dump($a[] *= 991);		// creates $a[0]
var_dump($a);

echo "====== array without index; ++/-- =========\n";

$a = array(3 => 33, -1 => -11);
//$a = array('x' => 33, 'y' => -11);
//$a = array();
var_dump($a);
echo "------\n";

//var_dump($a[]);

var_dump($a[]++);
var_dump($a);
echo "------\n";

var_dump(++$a[]);
var_dump($a);
echo "------\n";

var_dump(--$a[]);
var_dump($a);

echo "====== object; set up =========\n";

class C10 implements ArrayAccess
{
	function offsetExists($offset)
	{
		echo "\nInside " . __METHOD__ . "\n"; var_dump($offset);
	}
	function offsetGet($offset)
	{
		echo "\nInside " . __METHOD__ . "\n"; var_dump($offset); return 100;
	}
	function offsetSet($offset, $value)
	{
		echo "\nInside " . __METHOD__ . "\n"; var_dump($offset); var_dump($value);
	}
	function offsetUnset($offset)
	{
		echo "\nInside " . __METHOD__ . "\n"; var_dump($offset);
	}
}

$c10 = new C10;

echo "====== object with index; as non-lvalue =========\n";

var_dump($c10[1]);
echo "------\n";

var_dump($c10[1000]);
echo "------\n";

var_dump($c10[-123]);
echo "------\n";

var_dump($c10['abc']);

echo "====== object with index; simple assignment =========\n";

var_dump($c10[1] = 34);
echo "------\n";

var_dump($c10[1000] = 34);
echo "------\n";

var_dump($c10[-123] = 34);
echo "------\n";

var_dump($c10['abc'] = 34);

echo "====== object with index; compound assignment =========\n";

var_dump($c10[1000] += 7);

echo "====== object with index; ++/-- =========\n";

var_dump($c10[1000]++);
echo "------\n";
var_dump(--$c10[1000]);

echo "====== object without index; simple assignment =========\n";

var_dump($c10[] = 987);
echo "------\n";

var_dump($c10[] = TRUE);
echo "------\n";

var_dump($c10[] = 'xyz');

echo "====== object without index; compound assignment =========\n";

var_dump($c10[] += 5);
echo "------\n";

var_dump($c10[] -= 5);
echo "------\n";

var_dump($c10[] *= 5);

echo "====== object without index; ++/-- =========\n";

var_dump($c10);
echo "------\n";

var_dump($c10[]++);
echo "------\n";
var_dump(++$c10[]);
echo "------\n";

var_dump(--$c10[]);
--EXPECTF--
====== array without index; simple assignment =========
int(991)
array(3) {
  [3]=>
  int(33)
  [-1]=>
  int(-11)
  [4]=>
  int(991)
}
------
int(991)
array(3) {
  [-30]=>
  int(33)
  [-10]=>
  int(-11)
  [0]=>
  int(991)
}
------
int(991)
array(3) {
  [0]=>
  int(33)
  [-10]=>
  int(11)
  [1]=>
  int(991)
}
------
int(991)
array(3) {
  ["a"]=>
  int(33)
  ["x"]=>
  int(-11)
  [0]=>
  int(991)
}
====== array without index; compound assignment =========
int(991)
array(3) {
  ["a"]=>
  int(33)
  ["x"]=>
  int(-11)
  [0]=>
  int(991)
}
------
int(-991)
array(3) {
  ["a"]=>
  int(33)
  ["x"]=>
  int(-11)
  [0]=>
  int(-991)
}
------
int(0)
array(3) {
  ["a"]=>
  int(33)
  ["x"]=>
  int(-11)
  [0]=>
  int(0)
}
====== array without index; ++/-- =========
array(2) {
  [3]=>
  int(33)
  [-1]=>
  int(-11)
}
------
NULL
array(3) {
  [3]=>
  int(33)
  [-1]=>
  int(-11)
  [4]=>
  int(1)
}
------
int(1)
array(4) {
  [3]=>
  int(33)
  [-1]=>
  int(-11)
  [4]=>
  int(1)
  [5]=>
  int(1)
}
------
NULL
array(5) {
  [3]=>
  int(33)
  [-1]=>
  int(-11)
  [4]=>
  int(1)
  [5]=>
  int(1)
  [6]=>
  NULL
}
====== object; set up =========
====== object with index; as non-lvalue =========

Inside C10::offsetGet
int(1)
int(100)
------

Inside C10::offsetGet
int(1000)
int(100)
------

Inside C10::offsetGet
int(-123)
int(100)
------

Inside C10::offsetGet
string(3) "abc"
int(100)
====== object with index; simple assignment =========

Inside C10::offsetSet
int(1)
int(34)
int(34)
------

Inside C10::offsetSet
int(1000)
int(34)
int(34)
------

Inside C10::offsetSet
int(-123)
int(34)
int(34)
------

Inside C10::offsetSet
string(3) "abc"
int(34)
int(34)
====== object with index; compound assignment =========

Inside C10::offsetGet
int(1000)

Inside C10::offsetSet
int(1000)
int(107)
int(107)
====== object with index; ++/-- =========

Inside C10::offsetGet
int(1000)

Notice: Indirect modification of overloaded element of C10 has no effect in %s/expressions/postfix_operators/subscripting_2.php on line 117
int(100)
------

Inside C10::offsetGet
int(1000)

Notice: Indirect modification of overloaded element of C10 has no effect in %s/expressions/postfix_operators/subscripting_2.php on line 119
int(99)
====== object without index; simple assignment =========

Inside C10::offsetSet
NULL
int(987)
int(987)
------

Inside C10::offsetSet
NULL
bool(true)
bool(true)
------

Inside C10::offsetSet
NULL
string(3) "xyz"
string(3) "xyz"
====== object without index; compound assignment =========

Inside C10::offsetGet
NULL

Inside C10::offsetSet
NULL
int(105)
int(105)
------

Inside C10::offsetGet
NULL

Inside C10::offsetSet
NULL
int(95)
int(95)
------

Inside C10::offsetGet
NULL

Inside C10::offsetSet
NULL
int(500)
int(500)
====== object without index; ++/-- =========
object(C10)#1 (0) {
}
------

Inside C10::offsetGet
NULL

Notice: Indirect modification of overloaded element of C10 has no effect in %s/expressions/postfix_operators/subscripting_2.php on line 146
int(100)
------

Inside C10::offsetGet
NULL

Notice: Indirect modification of overloaded element of C10 has no effect in %s/expressions/postfix_operators/subscripting_2.php on line 148
int(101)
------

Inside C10::offsetGet
NULL

Notice: Indirect modification of overloaded element of C10 has no effect in %s/expressions/postfix_operators/subscripting_2.php on line 151
int(99)
