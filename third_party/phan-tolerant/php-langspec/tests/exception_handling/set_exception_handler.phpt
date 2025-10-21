--TEST--
PHP Spec test generated from ./exception_handling/set_exception_handler.php
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
	foreach ($traceInfo as $traceInfoKey => $traceLevel)	// process all traceback levels
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

$prev = set_exception_handler(NULL);	// set to default handler
var_dump($prev);

// define a default un-caught exception handler

function MyDefExHandler(Exception $e)
{
	echo "In MyDefExHandler\n";
	displayExceptionObject($e);
	echo "Leaving MyDefExHandler\n";
}

// establish a new un-caught exception handler

$prev = set_exception_handler("MyDefExHandler");	// use my handler
var_dump($prev);

// try it out

function f($p1, $p2)
{
try {
	echo "In try-block\n";

	throw new Exception("Watson, come here!", 1234);
}

// no catch block(s)

finally {
	echo "In finally-block\n";
}

echo "Beyond try/catch/finally blocks\n==========\n";
}

/*
restore_exception_handler();
*/

echo "About to call f\n";
f(10, TRUE);
echo "Beyond the call to f()\n";	// never gets here; script terminates after my handler ends
--EXPECTF--
NULL
NULL
About to call f
In try-block
In finally-block
In MyDefExHandler
$e = >Exception: Watson, come here! in %s/exception_handling/set_exception_handler.php:73
Stack trace:
#0 %s/exception_handling/set_exception_handler.php(90): f(10, true)
#1 {main}<
getMessage:       >Watson, come here!<
getCode:          >1234<
getPrevious:      ><
getFile:          >%s/exception_handling/set_exception_handler.php<
getLine:          >73<
getTraceAsString: >#0 %s/exception_handling/set_exception_handler.php(90): f(10, true)
#1 {main}<
array(1) {
  [0]=>
  array(4) {
    ["file"]=>
    string(%d) "%s/exception_handling/set_exception_handler.php"
    ["line"]=>
    int(90)
    ["function"]=>
    string(1) "f"
    ["args"]=>
    array(2) {
      [0]=>
      int(10)
      [1]=>
      bool(true)
    }
  }
}
Trace Info:
Key[0]:
  Key[file] => >%s/exception_handling/set_exception_handler.php<
  Key[line] => >90<
  Key[function] => >f<
  Key[args]:
    Key[0] => >10<
    Key[1] => >1<
Leaving MyDefExHandler
