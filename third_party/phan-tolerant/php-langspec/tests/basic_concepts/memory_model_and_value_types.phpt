--TEST--
PHP Spec test generated from ./basic_concepts/memory_model_and_value_types.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

const CON = TRUE;

///*
echo "----------------- simple assignment of value types ----------------------\n";

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

$b = $a;

echo "After '\$b = \$a', \$b is $b\n";

++$b;

echo "After '++\$b', \$b is $b, and \$a is $a\n";

$a = 99;

echo "After '\$a = 99', \$b is $b, and \$a is $a\n";
echo "Done\n";
//*/

///*
echo "----------------- byRef assignment of value types ----------------------\n";

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

$c =& $a;

echo "After '\$c =& \$a', \$c is $c, and \$a is $a\n";

++$c;

echo "After '++\$c', \$c is $c, and \$a is $a\n";

$a = 99;

echo "After '\$a = 99', \$c is $c, and \$a is $a\n";

unset($a);

echo "After 'unset(\$a)', \$c is $c, and \$a is undefined\n";
echo "Done\n";
//*/

///*
echo "----------------- value argument passing of value types ----------------------\n";

function f1($b)
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b = "abc";

	echo "After '\$b = \"abc\"', \$b is $b\n";
}

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

f1($a);

echo "After 'f1(\$a)', \$a is $a\n";

f1($a + 2);		// non-lvalue
f1(999);		// non-lvalue
f1(CON);		// non-lvalue
echo "Done\n";
//*/

///*
echo "-----------------  byRef argument passing of value types ----------------------\n";

function g1(&$b)
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b = "abc";

	echo "After '\$b = \"abc\"', \$b is $b\n";
}

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

g1($a);

echo "After 'g1(\$a)', \$a is $a\n";

//g1($a + 2);		// non-lvalue; can't be passed by reference
//g1(999)			// non-lvalue; can't be passed by reference
//g1(CON);			// non-lvalue; can't be passed by reference
echo "Done\n";
//*/

///*
echo "----------------- value returning of value types ----------------------\n";

function f2()
{
	$b = "abc";

	echo "After '\$b = \"abc\"', \$b is $b\n";

	return $b;
}

$a = f2();

echo "After '\$a = f2()', \$a is $a\n";
echo "Done\n";
//*/

///*
echo "----------------- byRef returning of value types ----------------------\n";

function & g2()
{
	$b = "abc";

	echo "After '\$b = \"abc\"', \$b is $b\n";

	return $b;
}

$a = g2();

echo "After '\$a = f2()', \$a is $a\n";
echo "Done\n";
//*/

///*
echo "----- test using literals, constants, and arbitrary-complex expressions ----\n";

//$a =& 12;		// literals are disallowed
//$a =& CON;	// constants are disallowed

$b = 10;
$a =& $b;
echo "After '=&', \$a is $a, \$b is $b\n";
//$a =& 5 + $b;	// arbitrary-complex expressions are disallowed
echo "After '=&', \$a is $a, \$b is $b\n";

function h1()
{
	$b = 10;
	return $b + 5;
//	return 12;
//	return CON;
}

echo "h1() is " . h1() . "\n";
//*/

///*
function & h2()
{
	$b = 10;
//	return $b + 5;	// Only variable references should be returned by reference
//	return 12;
//	return CON;
}

h2();
echo "Done\n";
//*/
--EXPECTF--
----------------- simple assignment of value types ----------------------
After '$a = 123', $a is 123
After '$b = $a', $b is 123
After '++$b', $b is 124, and $a is 123
After '$a = 99', $b is 124, and $a is 99
Done
----------------- byRef assignment of value types ----------------------
After '$a = 123', $a is 123
After '$c =& $a', $c is 123, and $a is 123
After '++$c', $c is 124, and $a is 124
After '$a = 99', $c is 99, and $a is 99
After 'unset($a)', $c is 99, and $a is undefined
Done
----------------- value argument passing of value types ----------------------
After '$a = 123', $a is 123
	Inside function f1, $b is 123
After '$b = "abc"', $b is abc
After 'f1($a)', $a is 123
	Inside function f1, $b is 125
After '$b = "abc"', $b is abc
	Inside function f1, $b is 999
After '$b = "abc"', $b is abc
	Inside function f1, $b is 1
After '$b = "abc"', $b is abc
Done
-----------------  byRef argument passing of value types ----------------------
After '$a = 123', $a is 123
	Inside function g1, $b is 123
After '$b = "abc"', $b is abc
After 'g1($a)', $a is abc
Done
----------------- value returning of value types ----------------------
After '$b = "abc"', $b is abc
After '$a = f2()', $a is abc
Done
----------------- byRef returning of value types ----------------------
After '$b = "abc"', $b is abc
After '$a = f2()', $a is abc
Done
----- test using literals, constants, and arbitrary-complex expressions ----
After '=&', $a is 10, $b is 10
After '=&', $a is 10, $b is 10
h1() is 15%A
Done
