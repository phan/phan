--TEST--
PHP Spec test generated from ./statements/iteration/for.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

for ($i = 1; $i <= 10; ++$i)
{
	echo "$i\t".($i * $i)."\n";	// output a table of squares
}

// omit 1st and 3rd expressions

$i = 1;
for (; $i <= 10;):
	echo "$i\t".($i * $i)."\n";	// output a table of squares
	++$i;
endfor;

// omit all 3 expressions

$i = 1;
for (;;)
{
	if ($i > 10)
		break;
	echo "$i\t".($i * $i)."\n";	// output a table of squares
	++$i;
}

//  use groups of expressions

for ($a = 100, $i = 1; ++$i, $i <= 10; ++$i, $a -= 10)
{
	echo "$i\t$a\n";
}
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
2	100
4	90
6	80
8	70
10	60
