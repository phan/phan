/* Auto-generated from php/php-langspec tests */
<?php

interface I {}
class C implements I {}

$cl2 = function ($p1, $p2 = 100, array $p3, C $p4, I $p5)
{
	echo "Inside function >>" . __FUNCTION__ . "<<\n";
	echo "Inside method >>" . __METHOD__ . "<<\n";
   	// ...
};
var_dump($cl2);

echo "--\n";
var_dump(gettype($cl2));
echo "--\n";
var_dump($cl2);
echo "--\n";
var_dump($cl2 instanceof Closure);
echo "--\n";

$cl2(10, 20, [1,2], new C, new C);

