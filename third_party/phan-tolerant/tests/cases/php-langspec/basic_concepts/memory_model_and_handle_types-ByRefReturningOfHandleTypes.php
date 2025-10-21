/* Auto-generated from php/php-langspec tests */
<?php

function & g2()
{
	$b = new Point(5, 7);	// create first new point, and make $b an alias to it

	echo "After 'new Point(5, 7)', \$b is $b\n";

	return $b;	// return as though using $a =& $b
				// as $b goes away, remove its alias
}

$a = g2();

echo "After '\$a = f2()', \$a is $a\n";
unset($a);	// remove only alias from point, so destructor runs
echo "Done\n";
//*/

