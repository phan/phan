--TEST--
PHP Spec test generated from ./expressions/general/precedence.php
--FILE--
 <?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$a = 20;
$b = 10;
$c = 2;

echo '$a - $b / $c   = '.($a - $b / $c)."\n";
echo '$a - ($b / $c) = '.($a - ($b / $c))."\n";
echo '($a - $b) / $c = '.(($a - $b) / $c)."\n";
--EXPECT--
$a - $b / $c   = 15
$a - ($b / $c) = 15
($a - $b) / $c = 5
