--TEST--
PHP Spec test generated from ./statements/iteration/while.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$i = 1;
while ($i <= 10)
{
	echo "$i\t".($i * $i)."\n";
	++$i;
}

$i = 1;
while ($i <= 10):
	echo "$i\t".($i * $i)."\n";	// output a table of squares
	++$i;
endwhile;

$count = 0;
while (TRUE)
{
	if (++$count == 5)
		$done = TRUE;
	echo $count."\n";
	// ...
	if ($done)
		break;	// break out of the while loop
	// ...
}
--EXPECTF--
1	1
2	4
3	9
4	16
5	25
6	36
7	49
8	64
9	81
10	100
1	1
2	4
3	9
4	16
5	25
6	36
7	49
8	64
9	81
10	100
1

Notice: Undefined variable: done in %s/statements/iteration/while.php on line 3%d
2

Notice: Undefined variable: done in %s/statements/iteration/while.php on line 3%d
3

Notice: Undefined variable: done in %s/statements/iteration/while.php on line 3%d
4

Notice: Undefined variable: done in %s/statements/iteration/while.php on line 3%d
5
