--TEST--
PHP Spec test generated from ./expressions/primary_expressions/intrinsics_echo.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

include_once 'Point.inc';

$v1 = TRUE;
$v2 = 123;
$v3 = 20.3E2;
$v4 = NULL;
$v5 = "Hello";
$v6 = new Point(3, 5);

echo '>>' . $v1 . '|' . $v2 . "<<\n";
echo '>>' , $v1 , '|' , $v2 , "<<\n";
echo ('>>' . $v1 . '|' . $v2 . "<<\n");
echo (('>>') . ($v1) . ('|') . ($v2) . ("<<\n"));// outer parens are part of optional syntax
												 // inner ones are redundant grouping parens
//echo ('>>' , $v1 , '|' , $v2 , "<<\n");	// parens no allowed with commas

echo '>>' . $v3 . '|' . $v4 . '|' . $v5 . '|' . $v6 . "<<\n";
echo '>>' , $v3 , '|' , $v4 , '|' , $v5 , '|' , $v6 , "<<\n";

$v3 = "qqq{$v2}zzz";
var_dump($v3);
echo "$v3\n";
--EXPECT--
>>1|123<<
>>1|123<<
>>1|123<<
>>1|123<<
>>2030||Hello|(3,5)<<
>>2030||Hello|(3,5)<<
string(9) "qqq123zzz"
qqq123zzz
