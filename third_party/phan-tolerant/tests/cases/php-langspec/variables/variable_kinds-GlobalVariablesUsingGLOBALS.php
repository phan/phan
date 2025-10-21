/* Auto-generated from php/php-langspec tests */
<?php

$GLOBALS['done'] = FALSE;
var_dump($done);

$GLOBALS['min'] = 10;
$GLOBALS['max'] = 100;
$GLOBALS['average'] = NULL;

global $min, $max;		// allowed, but serve no purpose

function compute2($p)
{
	$GLOBALS['average'] = ($GLOBALS['max'] + $GLOBALS['min'])/2;

	if ($p)
	{
		$GLOBALS['result'] = 3.456;		// initializes a global, creating it if necessary
	}
}

compute2(TRUE);
echo "\$average = $average\n";
echo "\$result = $result\n";

//var_dump($GLOBALS);

