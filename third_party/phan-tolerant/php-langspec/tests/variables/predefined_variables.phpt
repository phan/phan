--TEST--
PHP Spec test generated from ./variables/predefined_variables.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

var_dump($argc);
var_dump($argv);
var_dump($_ENV);
var_dump($GLOBALS);
--EXPECTF--
int(1)
array(1) {
  [0]=>
  string(%d) "%s/variables/predefined_variables.php"
}
array(%d) {%a}
array(%d) {%a}
