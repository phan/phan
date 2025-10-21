/* Auto-generated from php/php-langspec tests */
<?php

function factorial($i)
{
	if ($i > 1) return $i * factorial($i - 1);
	else if ($i == 1) return $i;
	else return 0;
}

$result = factorial(10);
echo "\$result = $result\n";

