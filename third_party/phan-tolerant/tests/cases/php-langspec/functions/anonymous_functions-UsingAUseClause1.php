/* Auto-generated from php/php-langspec tests */
<?php

function compute(array $values)
{
	$count = 0;

	$callback = function () use (&$count)
	{
		echo "Inside method >>" . __METHOD__ . "<<\n";	// called {closure}
		++$count;
	};

	$callback();
	echo "\$count = $count\n";
	$callback();
	echo "\$count = $count\n";
}

compute([1,2,3]);

