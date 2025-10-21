/* Auto-generated from php/php-langspec tests */
<?php

const MAX_HEIGHT2 = 10.5; 		// define two c-constants
const UPPER_LIMIT2 = MAX_HEIGHT2;
define('COEFFICIENT_2', 2.345);	// define two d-constants
define('FAILURE2', TRUE);
echo "MAX_HEIGHT2 = " . MAX_HEIGHT2 . "\n";

function globalConst()
{
	echo "Inside " . __FUNCTION__ . "\n";
	echo "MAX_HEIGHT2 = " . MAX_HEIGHT2 . "\n";
	echo "COEFFICIENT_2 = " . COEFFICIENT_2 . "\n";
}

globalConst();

