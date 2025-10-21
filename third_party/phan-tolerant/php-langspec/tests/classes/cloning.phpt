--TEST--
PHP Spec test generated from ./classes/cloning.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

echo "================= play around a bit =================\n";

class C
{
	private $m;
	public function __construct($p1)
	{
		$this->m = $p1;
	}

///*
	public function __clone()
	{
		echo "Inside " . __METHOD__ . "\n";

//		return NULL;	// ignored; not passed along as the result of 'clone'
	}
//*/
}

$obj1 = new C(10);
var_dump($obj1);

$obj2 = clone $obj1;	// default action is to make a shallow copy
var_dump($obj2);

//$obj3 = $obj1->__clone();	// can't call directly!! Why is that?
//var_dump($obj3);

echo "================= Use cloning in Point class =================\n";

include_once 'Point2.inc';

echo "Point count = " . Point2::getPointCount() . "\n";
$p1 = new Point2;
var_dump($p1);
echo "Point count = " . Point2::getPointCount() . "\n";
$p2 = clone $p1;
var_dump($p2);
echo "Point count = " . Point2::getPointCount() . "\n";

var_dump($p3 = clone $p1);
echo "Point count = " . Point2::getPointCount() . "\n";

var_dump($p4 = clone $p1);
echo "Point count = " . Point2::getPointCount() . "\n";

echo "================= use chained cloning in a class heirarchy =================\n";

class Employee
{
	private $name;

	public function __construct($name)
	{
		$this->name = $name;
	}

	public function __clone()
	{
		echo "Inside " . __METHOD__ . "\n";
//		$v = parent::__clone(); // as class has no parent, this is diagnosed

		// make a copy of Employee object

		return 999;	// ignored; not passed along as the result of 'clone'

	}
}

class Manager extends Employee
{
	private $level;

	public function __construct($name, $level)
	{
		parent::__construct($name);
		$this->level = $level;
	}

	public function __clone()
	{
		echo "Inside " . __METHOD__ . "\n";

		$v = parent::__clone();
		echo "\n====>>>>"; var_dump($v);

// make a copy of Manager object

//		return 999;	// ignored; not passed along as the result of 'clone'

	}
}

$obj3 = new Manager("Smith", 23);
var_dump($obj3);

$obj4 = clone $obj3;
var_dump($obj4);
--EXPECTF--
================= play around a bit =================
object(C)#1 (1) {
  ["m":"C":private]=>
  int(10)
}
Inside C::__clone
object(C)#2 (1) {
  ["m":"C":private]=>
  int(10)
}
================= Use cloning in Point class =================
Point count = 0
object(Point2)#3 (2) {
  ["x"]=>
  int(0)
  ["y"]=>
  int(0)
}
Point count = 1
Inside Point2::__clone, point count = 2
object(Point2)#4 (2) {
  ["x"]=>
  int(0)
  ["y"]=>
  int(0)
}
Point count = 2
Inside Point2::__clone, point count = 3
object(Point2)#5 (2) {
  ["x"]=>
  int(0)
  ["y"]=>
  int(0)
}
Point count = 3
Inside Point2::__clone, point count = 4
object(Point2)#6 (2) {
  ["x"]=>
  int(0)
  ["y"]=>
  int(0)
}
Point count = 4
================= use chained cloning in a class heirarchy =================
object(Manager)#7 (2) {
  ["level":"Manager":private]=>
  int(23)
  ["name":"Employee":private]=>
  string(5) "Smith"
}
Inside Manager::__clone
Inside Employee::__clone

====>>>>int(999)
object(Manager)#8 (2) {
  ["level":"Manager":private]=>
  int(23)
  ["name":"Employee":private]=>
  string(5) "Smith"
}
