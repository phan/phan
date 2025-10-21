/* Auto-generated from php/php-langspec tests */
<?php

function f1($b)
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b = "abc";

	echo "After '\$b = \"abc\"', \$b is $b\n";
}

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

f1($a);

echo "After 'f1(\$a)', \$a is $a\n";

f1($a + 2);		// non-lvalue
f1(999);		// non-lvalue
f1(CON);		// non-lvalue
echo "Done\n";
//*/

///*
