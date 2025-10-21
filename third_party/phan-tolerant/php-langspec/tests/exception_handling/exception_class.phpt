--TEST--
PHP Spec test generated from ./exception_handling/exception_class.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// ---------------------------------------------------------------

function displayExceptionObject(Exception $e)
{
	echo "\$e = >$e<\n";		// calls __toString
	echo "getMessage:       >".$e->getMessage()."<\n";
	echo "getCode:          >".$e->getCode()."<\n";
	echo "getPrevious:      >".$e->getPrevious()."<\n";
	echo "getFile:          >".$e->getFile()."<\n";
	echo "getLine:          >".$e->getLine()."<\n";
	echo "getTraceAsString: >".$e->getTraceAsString()."<\n";

	$traceInfo = $e->getTrace();
	var_dump($traceInfo);
	echo "Trace Info:".((count($traceInfo) == 0) ? " none\n" : "\n");
	foreach ($traceInfo as $traceInfoKey => $traceLevel)						// process all traceback levels
	{
		echo "Key[$traceInfoKey]:\n";
		foreach ($traceLevel as $levelKey => $levelVal)		// process one traceback level
		{
			if ($levelKey != "args")
			{
				echo "  Key[$levelKey] => >$levelVal<\n";
			}
			else
			{
				echo "  Key[$levelKey]:\n";
				foreach ($levelVal as $argKey => $argVal)	// process all args for that level
				{
					echo "    Key[$argKey] => >$argVal<\n";
				}
			}
		}
	}
}

// ---------------------------------------------------------------

function fL1($p1 = -10)
{
try {
	echo "fL1: In try-block\n";

//	throw new Exception();
	throw new Exception("fL1 Message", 123);
}
catch (Exception $e) {
	echo "fL1: In catch-block\n";

	displayExceptionObject($e);
}
finally {
	echo "fL1: In finally-block\n";
}

echo "fL1: Beyond try/catch/finally blocks\n==========\n";
echo "fL1: Calling fL2\n";
$a = -4.5;
fL2(2.3, $a);	// pass 2nd arg as a non-literal to see how traceback handles it
//fL2(2.3);	// see what happens when a default argument value is used
}

// ---------------------------------------------------------------

function fL2($p1, $p2 = -100)
{
try {
	echo "fL2: In try-block\n";

//	throw new Exception();
	throw new Exception("fL2 Message", 234);
}
catch (Exception $e) {
	echo "fL2: In catch-block\n";

	displayExceptionObject($e);
}
finally {
	echo "fL2: In finally-block\n";
}

echo "fL2: Beyond try/catch/finally blocks\n==========\n";
echo "fL2: Calling fL3\n";
$a = "xyz"; $b = NULL; $c = TRUE;
fL3($a, $b, $c); // pass args as a non-literal to see how traceback handles them
//fL3($a, $b);	// see what happens when a default argument value is used
}

// ---------------------------------------------------------------

function fL3($p1, $p2, $p3 = -1000)
{
try {
	echo "fL3: In try-block\n";

//	throw new Exception();
	throw new Exception("fL3 Message", 345);
}
catch (Exception $e) {
	echo "fL3: In catch-block\n";

	displayExceptionObject($e);
}
finally {
	echo "fL3: In finally-block\n";
}

echo "fL3: Beyond try/catch/finally blocks\n==========\n";
}

// ---------------------------------------------------------------

try {
	echo "L0: In try-block\n";

//	throw new Exception();
	throw new Exception("L0 Message", -1);
}
catch (Exception $e) {
	echo "L0: In catch-block\n";

	displayExceptionObject($e);
}
finally {
	echo "L0: In finally-block\n";
}

echo "L0: Beyond try/catch/finally blocks\n==========\n";
echo "L0: Calling fL1\n";
fL1(10);
//fL1();	// see what happens when a default argument value is used
--EXPECTF--
L0: In try-block
L0: In catch-block
$e = >Exception: L0 Message in %s/exception_handling/exception_class.php:127
Stack trace:
#0 {main}<
getMessage:       >L0 Message<
getCode:          >-1<
getPrevious:      ><
getFile:          >%s/exception_handling/exception_class.php<
getLine:          >127<
getTraceAsString: >#0 {main}<
array(0) {
}
Trace Info: none
L0: In finally-block
L0: Beyond try/catch/finally blocks
==========
L0: Calling fL1
fL1: In try-block
fL1: In catch-block
$e = >Exception: fL1 Message in %s/exception_handling/exception_class.php:55
Stack trace:
#0 %s/exception_handling/exception_class.php(140): fL1(10)
#1 {main}<
getMessage:       >fL1 Message<
getCode:          >123<
getPrevious:      ><
getFile:          >%s/exception_handling/exception_class.php<
getLine:          >55<
getTraceAsString: >#0 %s/exception_handling/exception_class.php(140): fL1(10)
#1 {main}<
array(1) {
  [0]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/exception_class.php"
    ["line"]=>
    int(140)
    ["function"]=>
    string(3) "fL1"
    ["args"]=>
    array(1) {
      [0]=>
      %Sint(10)
    }
  }
}
Trace Info:
Key[0]:
  Key[file] => >%s/exception_handling/exception_class.php<
  Key[line] => >140<
  Key[function] => >fL1<
  Key[args]:
    Key[0] => >10<
fL1: In finally-block
fL1: Beyond try/catch/finally blocks
==========
fL1: Calling fL2
fL2: In try-block
fL2: In catch-block
$e = >Exception: fL2 Message in %s/exception_handling/exception_class.php:81
Stack trace:
#0 %s/exception_handling/exception_class.php(69): fL2(2.3, -4.5)
#1 %s/exception_handling/exception_class.php(140): fL1(10)
#2 {main}<
getMessage:       >fL2 Message<
getCode:          >234<
getPrevious:      ><
getFile:          >%s/exception_handling/exception_class.php<
getLine:          >81<
getTraceAsString: >#0 %s/exception_handling/exception_class.php(69): fL2(2.3, -4.5)
#1 %s/exception_handling/exception_class.php(140): fL1(10)
#2 {main}<
array(2) {
  [0]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/exception_class.php"
    ["line"]=>
    int(69)
    ["function"]=>
    string(3) "fL2"
    ["args"]=>
    array(2) {
      [0]=>
      %Sfloat(2.3)
      [1]=>
      %Sfloat(-4.5)
    }
  }
  [1]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/exception_class.php"
    ["line"]=>
    int(140)
    ["function"]=>
    string(3) "fL1"
    ["args"]=>
    array(1) {
      [0]=>
      %Sint(10)
    }
  }
}
Trace Info:
Key[0]:
  Key[file] => >%s/exception_handling/exception_class.php<
  Key[line] => >69<
  Key[function] => >fL2<
  Key[args]:
    Key[0] => >2.3<
    Key[1] => >-4.5<
Key[1]:
  Key[file] => >%s/exception_handling/exception_class.php<
  Key[line] => >140<
  Key[function] => >fL1<
  Key[args]:
    Key[0] => >10<
fL2: In finally-block
fL2: Beyond try/catch/finally blocks
==========
fL2: Calling fL3
fL3: In try-block
fL3: In catch-block
$e = >Exception: fL3 Message in %s/exception_handling/exception_class.php:107
Stack trace:
#0 %s/exception_handling/exception_class.php(95): fL3('xyz', NULL, true)
#1 %s/exception_handling/exception_class.php(69): fL2(2.3, -4.5)
#2 %s/exception_handling/exception_class.php(140): fL1(10)
#3 {main}<
getMessage:       >fL3 Message<
getCode:          >345<
getPrevious:      ><
getFile:          >%s/exception_handling/exception_class.php<
getLine:          >107<
getTraceAsString: >#0 %s/exception_handling/exception_class.php(95): fL3('xyz', NULL, true)
#1 %s/exception_handling/exception_class.php(69): fL2(2.3, -4.5)
#2 %s/exception_handling/exception_class.php(140): fL1(10)
#3 {main}<
array(3) {
  [0]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/exception_class.php"
    ["line"]=>
    int(95)
    ["function"]=>
    string(3) "fL3"
    ["args"]=>
    array(3) {
      [0]=>
      %Sstring(3) "xyz"
      [1]=>
      %SNULL
      [2]=>
      %Sbool(true)
    }
  }
  [1]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/exception_class.php"
    ["line"]=>
    int(69)
    ["function"]=>
    string(3) "fL2"
    ["args"]=>
    array(2) {
      [0]=>
      %Sfloat(2.3)
      [1]=>
      %Sfloat(-4.5)
    }
  }
  [2]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/exception_class.php"
    ["line"]=>
    int(140)
    ["function"]=>
    string(3) "fL1"
    ["args"]=>
    array(1) {
      [0]=>
      %Sint(10)
    }
  }
}
Trace Info:
Key[0]:
  Key[file] => >%s/exception_handling/exception_class.php<
  Key[line] => >95<
  Key[function] => >fL3<
  Key[args]:
    Key[0] => >xyz<
    Key[1] => ><
    Key[2] => >1<
Key[1]:
  Key[file] => >%s/exception_handling/exception_class.php<
  Key[line] => >69<
  Key[function] => >fL2<
  Key[args]:
    Key[0] => >2.3<
    Key[1] => >-4.5<
Key[2]:
  Key[file] => >%s/exception_handling/exception_class.php<
  Key[line] => >140<
  Key[function] => >fL1<
  Key[args]:
    Key[0] => >10<
fL3: In finally-block
fL3: Beyond try/catch/finally blocks
==========
