--TEST--
PHP Spec test generated from ./expressions/general/sequence_points.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$a = 10;
echo '$a = '.$a."\n";
++$a;
echo '$a = '.$a."\n";
$b = $a;
echo '$b = '.$b."\n";
--EXPECT--
$a = 10
$a = 11
$b = 11
