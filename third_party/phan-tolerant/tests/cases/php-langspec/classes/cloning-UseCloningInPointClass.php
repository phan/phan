/* Auto-generated from php/php-langspec tests */
<?php

include_once 'Point2.inc';

echo "Point count = " . Point2::getPointCount() . "\n";
$p1 = new Point2;
var_dump($p1);
echo "Point count = " . Point2::getPointCount() . "\n";
$p2 = clone $p1;
var_dump($p2);
echo "Point count = " . Point2::getPointCount() . "\n";

var_dump($p3 = clone $p1);
echo "Point count = " . Point2::getPointCount() . "\n";

var_dump($p4 = clone $p1);
echo "Point count = " . Point2::getPointCount() . "\n";

