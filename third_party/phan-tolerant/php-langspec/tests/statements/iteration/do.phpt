--TEST--
PHP Spec test generated from ./statements/iteration/do.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$i = 1;
do
{
	echo "$i\t".($i * $i)."\n";	// output a table of squares
	++$i;
}
while ($i <= 10);
--EXPECT--
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
