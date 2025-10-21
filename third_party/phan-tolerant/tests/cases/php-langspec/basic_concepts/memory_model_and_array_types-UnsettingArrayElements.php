/* Auto-generated from php/php-langspec tests */
<?php

$x = 123;
$a = array(10, 'M' => TRUE, &$x, 'B' => new Point(1, 3));

echo "at start, \$x is $x, \$a is "; var_dump($a);

unset($a[0]);
echo "after unset(\$a[0]), \$x is $x, \$a is "; var_dump($a);

unset($a['M']);
echo "after unset(\$a['M']), \$x is $x, \$a is "; var_dump($a);

unset($a[1]);
echo "after unset(\$a[1]), \$x is $x, \$a is "; var_dump($a);

//unset($a['B']);
//echo "after unset(\$a['B']), \$x is $x, \$a is "; var_dump($a);

unset($a);
echo "after unset(\$a), \$x is $x, \$a is undefined\n";
//*/
