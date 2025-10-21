--TEST--
PHP Spec test generated from ./classes/point2_test1.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

include_once 'Point2.inc';

$p1 = new Point2;
$p2 = new Point2(-4,3);
$p3 = new Point2(20,30);

echo "Point count = " . Point2::getPointCount() . "\n";
$cName = 'Point2';
echo "Point count = " . $cName::getPointCount() . "\n";
--EXPECT--
Point count = 3
Point count = 3
