/* Auto-generated from php/php-langspec tests */
<?php

$a = 123;

echo "After '\$a = 123', \$a is $a\n";

$c =& $a;

echo "After '\$c =& \$a', \$c is $c, and \$a is $a\n";

++$c;

echo "After '++\$c', \$c is $c, and \$a is $a\n";

$a = 99;

echo "After '\$a = 99', \$c is $c, and \$a is $a\n";

unset($a);

echo "After 'unset(\$a)', \$c is $c, and \$a is undefined\n";
echo "Done\n";
//*/

///*
