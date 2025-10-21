/* Auto-generated from php/php-langspec tests */
<?php

$a = new Point(1, 3);	// create first new point, and make $a an alias to it

echo "After '\$a = new Point(1, 3)', \$a is $a\n";

$c =& $a;			// make $c forever alias whatever $a aliases

echo "After '\$c =& \$a', \$c is $c, and \$a is $a\n";

$a->move(4, 6);		// moving $a also moves $c

echo "After '\$a->move(4, 6)', \$c is $c, and \$a is $a\n";

$a = new Point(2, 1);	// remove $a's alias from first point
						// create second new point, and make $a an alias to it
						// As $c aliases whatever $a aliases, $c's old alias to the first
						// point is also removed, allowing the destructor to run.
						// $c's new alias is to the new point

echo "After '\$a = new Point(2, 1)', \$c is $c, and \$a is $a\n";

unset($a);	// remove one alias from second point
echo "After 'unset(\$a)', \$c is $c\n";
unset($c);	// remove second (and final) alias from second point, so destructor runs
echo "Done\n";
//*/

///*
