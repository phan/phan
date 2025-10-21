/* Auto-generated from php/php-langspec tests */
<?php

function g1(&$b)	// make $b alias whatever $a aliases
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b->move(4, 6);			// moving $b also moves $a
	echo "After '\$b->move(4, 6)', \$b is $b\n";

	$b = new Point(5, 7);	// removes second alias from first point;
							// then create first alias to second new point
							// changing $b also changes $a as well, so $a's alias
							// is also removed, alowing the destructor run

	echo "After 'new Point(5, 7)', \$b is $b\n";
} // $b goes away, remove its alias from new point

$a = new Point(1, 3);	// create first new point, and make $a an alias to it

echo "After '\$a = new Point(1, 3)', \$a is $a\n";

g1($a);		// $a is changed via change to $b

echo "After 'g1(\$a)', \$a is $a\n";
unset($a);	// remove only alias from point, so destructor runs
echo "Done\n";
//*/

///*
