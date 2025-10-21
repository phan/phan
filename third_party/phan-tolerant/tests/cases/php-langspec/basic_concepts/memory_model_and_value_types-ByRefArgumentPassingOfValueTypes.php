/* Auto-generated from php/php-langspec tests */
<?php

function g1(&$b)
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b = "abc";

	echo "After '\$b = \"abc\"', \$b is $b\n";
}

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

g1($a);

echo "After 'g1(\$a)', \$a is $a\n";

//g1($a + 2);		// non-lvalue; can't be passed by reference
//g1(999)			// non-lvalue; can't be passed by reference
//g1(CON);			// non-lvalue; can't be passed by reference
echo "Done\n";
//*/

///*
