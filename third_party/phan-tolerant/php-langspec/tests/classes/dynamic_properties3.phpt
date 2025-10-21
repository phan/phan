--TEST--
PHP Spec test generated from ./classes/dynamic_properties3.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

class C
{
  public function __get($name) {
    echo "get\n";
    return $this->$name;    // must not recurse
  }

  public function __set($name, $val) {
    echo "set\n";
    $this->$name = $val;    // must not recurse
  }

  public function __isset($name) {
    echo "isset\n";
    return isset($this->$name);    // must not recurse
  }

  public function __unset($name) {
    echo "unset\n";
    unset($this->$name);    // must not recurse
  }
}

$c = new C;
$x = $c->prop;  // Undefined property: C::$prop
$c->prop = 123; // Defined now
$x = $c->prop;
var_dump($x);
var_dump($c);
--EXPECTF--
get

Notice: Undefined property: C::$prop in %s/classes/dynamic_properties3.php on line 15
set
int(123)
object(C)#1 (1) {
  ["prop"]=>
  int(123)
}
