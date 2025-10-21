/* Auto-generated from php/php-langspec tests */
<?php

$x = 123;
$a = array(10, &$x, 'B' => new Point(1, 3));

echo "After '\$a = array(...)', \$a is "; var_dump($a);

$b = $a;

echo "After '\$b = \$a', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

++$b[0];

echo "After '++\$b[0]', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

$a[0] = 99;

echo "After '\$a[0] = 99', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

--$x;

echo "After '--\$x', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

unset($a);
echo "After 'unset(\$a)', \$a is undefined, \$b is "; var_dump($b);
unset($b);
echo "After 'unset(\$b)', \$b is undefined\n";
//*/

///*
