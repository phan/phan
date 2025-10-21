--TEST--
PHP Spec test generated from ./expressions/unary_operators/unary_arithmetic_operators.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

function DoIt($a)
{
	echo "--- start DoIt -------------------------\n\n";
	echo "     original: "; var_dump($a);
	$b = +$a;
//	echo "after unary +: "; var_dump($b);
	$c = -$a;
//	echo "after unary -: "; var_dump($c);
	$d = !$a;
//	echo "after unary !: "; var_dump($d);
	$e = ($a == 0);
//	echo "after $a == 0: "; var_dump($e);
/*
	$f = ~$a;
	echo "after unary ~: "; var_dump($f);
	printf(" before Hex: %08X\n", $a);
	printf(" after  Hex: %08X\n", $f);

	echo " before (int): ".(int)$a;
	printf("; before (int) Hex: %08X\n", $a);
*/
	echo "\n--- end DoIt -------------------------\n\n";
}

///*
// arithmetic operands

DoIt(0);
DoIt(5);
DoIt(-10);
DoIt(PHP_INT_MAX);
DoIt(-PHP_INT_MAX - 1);
DoIt(0.0);
DoIt(0.0000001e-100);
DoIt(12.7345);
DoIt(-9.34E26);
DoIt(PHP_INT_MAX + 10);
DoIt(1234567E50);
DoIt(1234567E100);
DoIt(INF);
DoIt(-INF);
DoIt(NAN);
DoIt(-NAN);
//*/

///*
// NULL operand

DoIt(NULL);			// ~ not supported, so disable cod eblock in DoIt when testing
//*/

//*
// Boolean operands

DoIt(TRUE);			// ~ not supported, so disable code block in DoIt when testing
DoIt(FALSE);		// ~ not supported, so disable code block in DoIt when testing
//*/

///*
// string operands

DoIt("0");
DoIt("-43");
DoIt("123");
DoIt("0.0");
DoIt("-25.5e-10");
DoIt("");
DoIt("ABC");
//*/
--EXPECTF--
--- start DoIt -------------------------

     original: int(0)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: int(5)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: int(-10)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: int(9223372036854775807)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: int(-9223372036854775808)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(0)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(1.0E-107)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(12.7345)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(-9.34E+26)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(9.2233720368548E+18)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(1.234567E+56)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(1.234567E+106)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(INF)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(-INF)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(NAN)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: float(NAN)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: NULL

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: bool(true)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: bool(false)

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(1) "0"

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(3) "-43"

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(3) "123"

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(3) "0.0"

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(9) "-25.5e-10"

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(0) ""

Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d

--- end DoIt -------------------------

--- start DoIt -------------------------

     original: string(3) "ABC"

Warning: A non-numeric value encountered in %s on line %d

Warning: A non-numeric value encountered in %s on line %d

--- end DoIt -------------------------
