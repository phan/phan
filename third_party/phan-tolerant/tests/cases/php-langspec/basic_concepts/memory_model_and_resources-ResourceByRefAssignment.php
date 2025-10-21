/* Auto-generated from php/php-langspec tests */
<?php

$a = STDIN;

echo "After '\$a = STDIN', \$a is $a\n";

$c =& $a;

echo "After '\$c =& \$a', \$c is $c, and \$a is $a\n";

$a = STDOUT;	// this causes $c to also alias 99

echo "After '\$a = STDOUT', \$c is $c, and \$a is $a\n";

unset($a);

echo "After 'unset(\$a)', \$c is $c, and \$a is undefined\n";
echo "Done\n";
//*/

///*
