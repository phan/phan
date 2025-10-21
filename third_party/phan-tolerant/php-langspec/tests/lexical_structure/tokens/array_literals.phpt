--TEST--
PHP Spec test generated from ./lexical_structure/tokens/array_literals.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

class X
{
	private $prop10 = array();
	private $prop11 = array(10, "a" => "red", TRUE);
	private $prop12 = array(10, "red", "xx" => array(2.3, NULL, "zz" => array(12, FALSE, "zzz")));

	private $prop20 = [];
	private $prop21 = [10, "a" => "red", TRUE];
	private $prop22 = [10, "red", "xx" => [2.3, NULL, "zz" => [12, FALSE, "zzz"]]];
}

$x = new X;
var_dump($x);
--EXPECT--
object(X)#1 (6) {
  ["prop10":"X":private]=>
  array(0) {
  }
  ["prop11":"X":private]=>
  array(3) {
    [0]=>
    int(10)
    ["a"]=>
    string(3) "red"
    [1]=>
    bool(true)
  }
  ["prop12":"X":private]=>
  array(3) {
    [0]=>
    int(10)
    [1]=>
    string(3) "red"
    ["xx"]=>
    array(3) {
      [0]=>
      float(2.3)
      [1]=>
      NULL
      ["zz"]=>
      array(3) {
        [0]=>
        int(12)
        [1]=>
        bool(false)
        [2]=>
        string(3) "zzz"
      }
    }
  }
  ["prop20":"X":private]=>
  array(0) {
  }
  ["prop21":"X":private]=>
  array(3) {
    [0]=>
    int(10)
    ["a"]=>
    string(3) "red"
    [1]=>
    bool(true)
  }
  ["prop22":"X":private]=>
  array(3) {
    [0]=>
    int(10)
    [1]=>
    string(3) "red"
    ["xx"]=>
    array(3) {
      [0]=>
      float(2.3)
      [1]=>
      NULL
      ["zz"]=>
      array(3) {
        [0]=>
        int(12)
        [1]=>
        bool(false)
        [2]=>
        string(3) "zzz"
      }
    }
  }
}
