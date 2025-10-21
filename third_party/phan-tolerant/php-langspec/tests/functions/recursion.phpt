--TEST--
PHP Spec test generated from ./functions/recursion.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// use recursion to implement a factorial function
// Note: can call a function prior to its definition

for ($i = 0; $i <= 10; ++$i)
{
	printf("%2d! = %d\n", $i, factorial($i));
}

function factorial($int)
{
	return ($int > 1) ? $int * factorial($int - 1) : $int;
}
--EXPECT--
0! = 0
 1! = 1
 2! = 2
 3! = 6
 4! = 24
 5! = 120
 6! = 720
 7! = 5040
 8! = 40320
 9! = 362880
10! = 3628800
