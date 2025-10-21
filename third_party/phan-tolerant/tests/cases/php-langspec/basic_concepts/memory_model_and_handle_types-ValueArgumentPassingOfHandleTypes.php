/* Auto-generated from php/php-langspec tests */
<?php

function f1($b) // pass-by-value creates second alias to first point
{
	echo "\tInside function " . __FUNCTION__ . ", \$b is $b\n";

	$b->move(4, 6);			// moving $b also moves $a
	echo "After '\$b->move(4, 6)', \$b is $b\n";

	$b = new Point(5, 7);	// removes second alias from first point;
							// then create first alias to second new point

	echo "After 'new Point(5, 7)', \$b is $b\n";
} // $b goes away, remove the only alias from second point, so destructor runs

$a = new Point(1, 3);	// create first new point, and make $a an alias to it

echo "After '\$a = new Point(1, 3)', \$a is $a\n";

f1($a);		// $a's point value is changed, but $a still aliases first point

echo "After 'f1(\$a)', \$a is $a\n";

unset($a);	// remove only alias from first point, so destructor runs
echo "Done\n";
//*/

///*
