/* Auto-generated from php/php-langspec tests */
<?php

function g1(&$b)
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b = STDOUT;

	echo "After '\$b = STDOUT', \$b is $b\n";
}

$a = STDIN;

echo "After '\$a = STDIN', \$a is $a\n";

g1($a);

echo "After 'g1(\$a)', \$a is $a\n";
echo "Done\n";
//*/

///*
