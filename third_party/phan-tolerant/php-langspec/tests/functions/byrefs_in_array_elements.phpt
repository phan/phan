--TEST--
PHP Spec test generated from ./functions/byrefs_in_array_elements.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// arrays containing byRefs

$x = 10;
$y = TRUE;
$z = 2.345;
var_dump($x);
var_dump($y);
var_dump($z);

$a = array($x, $y, $z);		// $a contains copies of the 3 variables
var_dump($a);

$a[1] = NULL;
var_dump($a);
var_dump($y);				// $y is unchanged

$a = array(&$x, &$y, &$z);	// $a contains byRefs to the 3 variables
var_dump($a);

$a[1] = NULL;				// change $y via a byRefs to it
var_dump($a);
var_dump($y);				// $y is changed

var_dump($a[0]);			// get int(10) as that is the type/value of the underlying thing
--EXPECT--
int(10)
bool(true)
float(2.345)
array(3) {
  [0]=>
  int(10)
  [1]=>
  bool(true)
  [2]=>
  float(2.345)
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  NULL
  [2]=>
  float(2.345)
}
bool(true)
array(3) {
  [0]=>
  &int(10)
  [1]=>
  &bool(true)
  [2]=>
  &float(2.345)
}
array(3) {
  [0]=>
  &int(10)
  [1]=>
  &NULL
  [2]=>
  &float(2.345)
}
NULL
int(10)
