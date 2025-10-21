/* Auto-generated from php/php-langspec tests */
<?php

$x = 123;
$a = array(10, &$x, 'B' => new Point(1, 3));

echo "After '\$a = array(...)', \$a is "; var_dump($a);

$c =& $a;

echo "After '\$c =& \$a', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

++$c[0];

echo "After '++\$c[0]', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

$a[0] = 99;

echo "After '\$a[0] = 99', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

--$x;

echo "After '--\$x', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

unset($a);
echo "After 'unset(\$a)', \$a is undefined, \$c is "; var_dump($c);

unset($c);
echo "End\n";
//*/

///*
