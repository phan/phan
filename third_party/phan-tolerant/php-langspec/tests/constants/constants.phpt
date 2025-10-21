--TEST--
PHP Spec test generated from ./constants/constants.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

function trace($name, $value, $b = FALSE)
{
	$r = define($name, $value, $b);
	echo "define $name " . ($r ? "succeeded" : "failed");
	if (defined($name))
		echo "; value is >" . constant($name)  . "<\n";
	else
		echo "; not defined\n";
}

///*
// define some constants with simple (single-token) scalar initial values

trace("STATUS1", TRUE);
trace("MIN", 10);
trace("MAX", 20, TRUE);		// HHVM Warning: Case insensitive constant names are not supported in HipHop
trace("MY_PI", 3.1415926);
trace("MY_COLOR", "red");
trace("C1", NULL);
//*/

///*
// try to define some constants with multiple-token scalar initial values

// involving literals only

trace("CON1", 5 + 10.1 * 3);// succeeded;

// involving variables and other non-basic operators

function getValue() { return 250; }

$v1 = 10;
trace("CON2", 5 + $v1);				// succeeded
$v2 = array(10, 20);
trace("CON3", 5 + $v2[0]);			// succeeded
trace("CON4", 5 + array(10, 20)[0]);	// succeeded
trace("CON5", 5 + ++$v2[0]);		// succeeded
trace("CON6", 5 + $v2[0]--);		// succeeded
trace("CON7", 5 + (float)$v1);		// succeeded
trace("CON8", 1 << $v1);			// succeeded
trace("CON9", $v2[0] == $v2[1]);	// succeeded
trace("CON10", $v2[0] = 123);		// succeeded
trace("CON11", getValue() - 100);	// succeeded

// involving constants

trace("CON21", 1 + CON1);			// succeeded;
trace("CON22", 2 * CON2 + CON1);	// succeeded;
//*/

///*
// try to define some constants with names not permitted as tokens

trace("36ABC", 100) . "\n";		// ill-formed name, but no error. Seems to work
trace("#%&", 200) . "\n";		// ill-formed name, but no error. Seems to work
//*/

///*
// try to redefine a user-defined constant

trace("MY_COLOR", "green");		// Warning, and doesn't change the value

// try to redefine a pre-defined constant

trace("TRUE", 999) . "\n";		// PHP5: No warning, and doesn't change the value, but
								// constant("TRUE") and get_defined_constants show 999!
								// HHVM:  Constant TRUE already defined ...
echo "    TRUE's value:" . TRUE . "\n";	// however, this shows the old value, 1
//*/

///*
// try to define some constants with non-scalar initial values

trace("COLORS", [10, 20]);	// Works from PHP 7 onwards

class C {}
trace("MY_OBJECT", new C);	// Constants may only evaluate to scalar values or arrays

$infile = fopen("Testfile.txt", 'r');
if ($infile == FALSE)
{
	echo "Can't open file\n";
}
trace("MY_RESOURCE", $infile);	// PHP5: Succeeded
								// HHVM: Constants may only evaluate to scalar values
trace("MY_RESOURCE", $infile);	// PHP5: Duplicate rejected
//*/

///*
class MyClass
{
//	define("DEFINE_INSIDE_CLASS", 10);		// not permitted inside a class; OK

//	const CON30;			// not permitted; OK
//	const CON31 = 5 + 10.1 * 3;// failed; unexpected '+', expecting ',' or ';'
//	const CON32 = $v1;		// failed; unexpected '$v1'
//	const CON33 = $v2[0];	// failed
//	const CON34 = array(10, 20)[0];	// failed; Arrays are not allowed in class constants
//	const CON35 = ++$v2[0];	// failed; unexpected '++'
//	const CON37 = (float)10;// failed; unexpected '(float)'	const CON38 = 1 << $v1);	// surprise? succeeded
//	const CON40 = new C;	// failed; unexpected 'new'
	const CON38 = 99;		// succeeded
	const CON39 = CON38;	// succeeded
}
//*/

///*
// Note: As opposed to defining constants using define(), constants defined using the
// const keyword must be declared at the top-level scope because they are defined at
// compile-time. This means that they cannot be declared inside functions, loops or
// if statements.

//	const CON50;			// not permitted; OK
//	const CON51 = 5 + 10.1 * 3;// failed; unexpected '+', expecting ',' or ';'
//	const CON52 = $v1;		// failed; unexpected '$v1'
//	const CON53 = $v2[0];	// failed
//	const CON54 = array(10, 20)[0];	// failed; Arrays are not allowed in class constants
//	const CON55 = ++$v2[0];	// failed; unexpected '++'
//	const CON57 = (float)10;	// failed; unexpected '(float)'	const CON38 = 1 << $v1);	// surprise? succeeded
//	const CON58 = new C;	// failed; unexpected 'new'
	const CON59 = 99;		// succeeded
	const CON60 = CON59;	// succeeded

	trace("CON61", 321);
	const CON62 = CON61;	// succeeded
	trace("CON63", CON62);

function f($p)
{
//	const CON70A = 10;		// unexpected 'const'
	trace("CON70B", 10);	// succeeded

	if ($p)
	{
//		const CON71A = 101;		// unexpected 'const'
		trace("CON71B", 101);	// succeeded
	}
}	

f(10);
//*/

///*
// try defining a constant whose name is a keyword

trace("FOR", 100);		// succeeded
// echo FOR;			// unexpected 'FOR' (T_FOR)

// const FOR = 100;		// unexpected 'FOR' (T_FOR)
//*/

class C3
{

	const CON1 = 123;			// implicitly static and can't say so explicitly
								// implicitly public

	public const CON2 = 123;	// explicitly public
	protected const CON3 = 123;	// protected constant
	private const CON4 = 123;	// private constant
}

echo "CON1: " . C3::CON1 . "\n";	// use :: notation, as a const is implicitly static

// public const CON80 = 80;		// top level const can not have visibility modifier
// protected const CON81 = 81;	// top level const can not have visibility modifier
// private const CON82 = 82;	// top level const can not have visibility modifier

//print_r(get_defined_constants());
--EXPECTF--
define STATUS1 succeeded; value is >1<
define MIN succeeded; value is >10<
define MAX succeeded; value is >20<
define MY_PI succeeded; value is >3.1415926<
define MY_COLOR succeeded; value is >red<
define C1 succeeded; value is ><
define CON1 succeeded; value is >35.3<
define CON2 succeeded; value is >15<
define CON3 succeeded; value is >15<
define CON4 succeeded; value is >15<
define CON5 succeeded; value is >16<
define CON6 succeeded; value is >16<
define CON7 succeeded; value is >15<
define CON8 succeeded; value is >1024<
define CON9 succeeded; value is ><
define CON10 succeeded; value is >123<
define CON11 succeeded; value is >150<
define CON21 succeeded; value is >36.3<
define CON22 succeeded; value is >65.3<
define 36ABC succeeded; value is >100<
define #%& succeeded; value is >200<

Notice: Constant MY_COLOR already defined in %s/constants/constants.php on line 13
define MY_COLOR failed; value is >red<
define TRUE succeeded; value is >999<
    TRUE's value:1
define COLORS succeeded
Notice: Array to string conversion in %s/constants/constants.php on line 16
; value is >Array<

Warning: Constants may only evaluate to scalar values or arrays in %s/constants/constants.php on line 13
define MY_OBJECT failed; not defined

Warning: fopen(Testfile.txt): failed to open stream: No such file or directory in %s/constants/constants.php on line 90
Can't open file
define MY_RESOURCE succeeded; value is ><

Notice: Constant MY_RESOURCE already defined in %s/constants/constants.php on line 13
define MY_RESOURCE failed; value is ><
define CON61 succeeded; value is >321<
define CON63 succeeded; value is >321<
define CON70B succeeded; value is >10<
define CON71B succeeded; value is >101<
define FOR succeeded; value is >100<
CON1: 123
