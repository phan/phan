/* Auto-generated from php/php-langspec tests */
<?php

function f()
{
	$lv = 1;
	static $fs = 1;
	static $fs2;
	var_dump($fs2);		// show default value is NULL

	echo "\$lv = $lv, \$fs = $fs\n";
	++$lv;
	++$fs;
	if (TRUE)
	{
		static $fs3 = 99;
		echo "\$fs3 = $fs3\n";
		++$fs3;
	}
}

for ($i = 1; $i <= 3; ++$i)
	f();

