/* Auto-generated from php/php-langspec tests */
<?php

$colors = array("red", "white", "blue");

$min = 10;
$max = 100;
$average = NULL;

global $min, $max;		// allowed, but serve no purpose

function compute($p)
{
	global $min, $max;
	global $average;
	$average = ($max + $min)/2;

	if ($p)
	{
		global $result;
		$result = 3.456;		// initializes a global, creating it if necessary
	}
}

compute(TRUE);
echo "\$average = $average\n";
echo "\$result = $result\n";

//var_dump($GLOBALS);

