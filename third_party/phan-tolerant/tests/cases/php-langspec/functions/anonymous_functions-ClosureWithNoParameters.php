/* Auto-generated from php/php-langspec tests */
<?php

$cl1 = function ()
{
	echo "Inside function >>" . __FUNCTION__ . "<<\n";
	echo "Inside method >>" . __METHOD__ . "<<\n";
   	// ...
};

echo "--\n";
var_dump(gettype($cl1));
echo "--\n";
var_dump($cl1);
echo "--\n";
var_dump($cl1 instanceof Closure);
echo "--\n";

$cl1();

// Closure object is empty

