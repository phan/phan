--TEST--
PHP Spec test generated from ./expressions/postfix_operators/member_selection_operator.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

class Point
{
	private $x;
	private $y;

	public function getX()	 	{ return $this->x; }
	public function setX($x)	{ $this->x = $x;   }
	public function getY()		{ return $this->y; }
	public function setY($y)	{ $this->y = $y;   }

	public function __construct($x = 0, $y = 0)
	{
		$name = "x";
		$this->$name = $x;	// member name as the value of a string is permitted
//		$this->"x" = $x;	// string literal not allowed, however
//		$this->x = $x;
		$this->y = $y;
	}

	public function move($x, $y)
	{
		$this->x = $x;
		$this->y = $y;
	}	

	public function translate($x, $y)
	{
		$this->x += $x;
		$this->y += $y;
	}

	public function __toString()
	{
		return '(' . $this->x . ',' . $this->y . ')';
	}

	public $piProp = 555;
	public static function psf() { return 123; }
	public static $psProp = 999;
	const MYPI = 3.14159;
}

$p1 = new Point;
echo "\$p1 is >$p1<\n";
///*
$p1->move(3, 9);
echo "\$p1 is >$p1<\n";

$n = "move";
$p1->$n(-2, 4);
echo "\$p1 is >$p1<\n";

$p1->color = "red";	// turned into $p1->__set("color", "red");
var_dump($p1);

$c = $p1->color;	// turned into $c = $p1->__get("color");
var_dump($c);
//*/

var_dump($p1->piProp);	// okay to access instance property via instance
var_dump($p1->psf());	// okay to access static method via instance
//var_dump(($p1->psf)());	// doesn't parse
//var_dump($p1->psf);		// so no surprise this won't work
var_dump($p1->psProp);	// Not okay. Strict Standards: Accessing static property
						// Point::$psProp as non static
						// Notice: Undefined property: Point::$psProp
						// NULL
var_dump($p1->MYPI);	// Not okay. Notice: Undefined property: Point::$MYPI
						// NULL


//var_dump(Point::piProp);// Fatal error: Undefined class constant 'piProp'
var_dump(Point::psf());	// okay to access static method via class
var_dump(Point::$psProp);// okay to access static property via class, but leading $ needed!!
var_dump(Point::MYPI);	// okay to access const via class

// use multiple ->s

class K
{
	public $prop;
	public function __construct(L $p1)
	{
		$this->prop = $p1;
	}
}

class L
{
	public function f() { echo "Hello from f\n"; }
}

$k = new K(new L);
$k->prop->f();
--EXPECTF--
$p1 is >(0,0)<
$p1 is >(3,9)<
$p1 is >(-2,4)<
object(Point)#1 (4) {
  ["x":"Point":private]=>
  int(-2)
  ["y":"Point":private]=>
  int(4)
  ["piProp"]=>
  int(555)
  ["color"]=>
  string(3) "red"
}
string(3) "red"
int(555)
int(123)

%ANotice: Undefined property: Point::$psProp in %s/expressions/postfix_operators/member_selection_operator.php on line 74
NULL

Notice: Undefined property: Point::$MYPI in %s/expressions/postfix_operators/member_selection_operator.php on line 78
NULL
int(123)
int(999)
float(3.14159)
Hello from f
