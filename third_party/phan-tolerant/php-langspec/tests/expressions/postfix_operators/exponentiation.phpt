--TEST--
PHP Spec test generated from ./expressions/postfix_operators/exponentiation.php
--FILE--
<?php

var_dump(2**3);
var_dump(2**3.0);
var_dump(2.0**3.0);
var_dump(2.111**3.111);
var_dump(2**"3");
var_dump("2"**3.0);
var_dump("2.0"**"3.0");
var_dump("2.111"**"3.111");

echo "===========\n";

$v = 5;
$v **= 3;
var_dump($v);
$v = 5;
$v **= 3.0;
var_dump($v);

echo "===========\n";

$r = 4 / 2 * 3 ** 2; // ** has higher precedence than * and /
var_dump($r);
$r = 4 / 2 * (3 ** 2);
var_dump($r);
$r = (4 / 2) * 3 ** 2;
var_dump($r);
$r = (4 / 2) * (3 ** 2);
var_dump($r);
$r = (((4 / 2) * 3) ** 2);
var_dump($r);

echo "===========\n";

var_dump(-3 ** 2);
var_dump((-3) ** 2);
var_dump(-(3 ** 2)); // ** has higher precedence than unary -

echo "===========\n";

$a = array(10, 20);
var_dump($a[0] ** 2);

function f() { return 3; }
var_dump(f() ** 2);

class C
{
  public $prop = 12.0;
}
$c = new C;
var_dump($c->prop ** 2);
--EXPECT--
int(8)
float(8)
float(8)
float(10.220753018608)
int(8)
float(8)
float(8)
float(10.220753018608)
===========
int(125)
float(125)
===========
int(18)
int(18)
int(18)
int(18)
int(36)
===========
int(-9)
int(9)
int(-9)
===========
int(100)
int(9)
float(144)
