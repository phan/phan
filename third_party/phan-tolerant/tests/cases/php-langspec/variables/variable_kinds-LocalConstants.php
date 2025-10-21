/* Auto-generated from php/php-langspec tests */
<?php

function localConst($p)
{
	echo "Inside " . __FUNCTION__ . "\n";
	define('COEFFICIENT_1', 2.345);	// define two d-constants
	echo "COEFFICIENT_1 = " . COEFFICIENT_1 . "\n";
	if ($p)
	{
		echo "COEFFICIENT_1 = " . COEFFICIENT_1 . "\n";
		define('FAILURE', TRUE);
		echo "FAILURE = " . FAILURE . "\n";
	}
}

localConst(TRUE);
echo "COEFFICIENT_1 = " . COEFFICIENT_1 . "\n";
echo "FAILURE = " . FAILURE . "\n";	// as it's visible here, it's not really a local!

