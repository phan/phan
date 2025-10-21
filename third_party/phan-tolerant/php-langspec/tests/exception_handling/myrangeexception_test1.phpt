--TEST--
PHP Spec test generated from ./exception_handling/myrangeexception_test1.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

include_once 'MyRangeException.inc';

$re = new MyRangeException("xxx", 5, 20, 30);
var_dump($re);

echo "=======\n";

echo "\$re = >$re<\n";
--EXPECTF--
object(MyRangeException)#1 (10) {
  ["badValue":"MyRangeException":private]=>
  int(5)
  ["lowerValue":"MyRangeException":private]=>
  int(20)
  ["upperValue":"MyRangeException":private]=>
  int(30)
  ["message":protected]=>
  string(3) "xxx"
  ["string":"Exception":private]=>
  string(0) ""
  ["code":protected]=>
  int(0)
  ["file":protected]=>
  string(%d) "%s/exception_handling/myrangeexception_test1.php"
  ["line":protected]=>
  int(13)
  ["trace":"Exception":private]=>
  array(0) {
  }
  ["previous":"Exception":private]=>
  NULL
}
=======
$re = >MyRangeException: xxx in %s/exception_handling/myrangeexception_test1.php:13
Stack trace:
#0 {main}, badValue: 5, lowerValue: 20, upperValue: 30<
