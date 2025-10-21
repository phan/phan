/* Auto-generated from php/php-langspec tests */
<?php

$a = new Point(1, 3);	// create first new point, and make $a an alias to it

echo "After '\$a = new Point(1, 3)', \$a is $a\n";

$b = $a;		// $b is a snapshot copy of $a, so create second alias to first point

echo "After '\$b = \$a', \$b is $b\n";

$d = clone $b;	// create second point, and make $d the first alias to that

echo "After '\$d = clone \$b', \$d is $d\n";

$b->move(4, 6);		// moving $b also moves $a, but $d is unchanged

echo "After '\$b->move(4, 6)', \$d is $d, \$b is $b, and \$a is $a\n";

$a = new Point(2, 1);	// remove $a's alias from first point
						// create third new point, and make $a an alias to it
						// As $b still aliases the first point, $b is unchanged

echo "After '\$a = new Point(2, 1)', \$d is $d, \$b is $b, and \$a is $a\n";

unset($a);	// remove only alias from third point, so destructor runs
unset($b);	// remove only alias from first point, so destructor runs
unset($d);	// remove only alias from second point, so destructor runs
echo "Done\n";
//*/

///*
