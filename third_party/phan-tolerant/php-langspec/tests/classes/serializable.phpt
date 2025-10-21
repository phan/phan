--TEST--
PHP Spec test generated from ./classes/serializable.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

class Point implements Serializable
{
	private static $nextId = 1;

	private $x;
	private $y;
	private $id;

	public function __construct($x = 0, $y = 0)
	{
		$this->x = $x;
		$this->y = $y;
		$this->id = self::$nextId++;

		echo "\nInside " . __METHOD__ . ", $this\n\n";
	}

	public function __toString()
	{
		return 'ID:' . $this->id . '(' . $this->x . ',' . $this->y . ')';
	}	

	public function serialize()
	{
		echo "\nInside " . __METHOD__ . ", $this\n\n";
		
		return serialize(array('y' => $this->y, 'x' => $this->x));
	}

    public function unserialize($data)
    {
		$data = unserialize($data);
		$this->x = $data['x'];
		$this->y = $data['y'];
		$this->id = self::$nextId++;

		echo "\nInside " . __METHOD__ . ", $this\n\n";
    }
}

echo "---------------- create, serialize, and unserialize a Point -------------------\n";

$p = new Point(2, 5);
echo "Point \$p = $p\n";

$s = serialize($p);
var_dump($s);

echo "------\n";

$v = unserialize($s);
var_dump($v);

echo "------\n";

class ColoredPoint extends Point implements Serializable
{
	const RED = 1;
	const BLUE = 2;

	private $color;

	public function __construct($x = 0, $y = 0, $color = RED)
	{
		parent::__construct($x, $y);
		$this->color = $color;

		echo "\nInside " . __METHOD__ . ", $this\n\n";
	}

	public function __toString()
	{
		return parent::__toString() . $this->color;
	}	

	public function serialize()
	{
		echo "\nInside " . __METHOD__ . ", $this\n\n";
		
		return serialize(array(
			'color' => $this->color,
			'baseData' => parent::serialize()
		));
	}

    public function unserialize($data)
    {
		$data = unserialize($data);
		$this->color = $data['color'];
		parent::unserialize($data['baseData']);

		echo "\nInside " . __METHOD__ . ", $this\n\n";
    }
}

echo "---------------- Serialize ColoredPoint -------------------\n";

$cp = new ColoredPoint(9, 8, ColoredPoint::BLUE);
echo "ColoredPoint \$cp = $cp\n";

$s = serialize($cp);
var_dump($s);

$v = unserialize($s);
var_dump($v);

echo "---------------- end -------------------\n";
--EXPECT--
---------------- create, serialize, and unserialize a Point -------------------

Inside Point::__construct, ID:1(2,5)

Point $p = ID:1(2,5)

Inside Point::serialize, ID:1(2,5)

string(47) "C:5:"Point":30:{a:2:{s:1:"y";i:5;s:1:"x";i:2;}}"
------

Inside Point::unserialize, ID:2(2,5)

object(Point)#2 (3) {
  ["x":"Point":private]=>
  int(2)
  ["y":"Point":private]=>
  int(5)
  ["id":"Point":private]=>
  int(2)
}
------
---------------- Serialize ColoredPoint -------------------

Inside Point::__construct, ID:3(9,8)


Inside ColoredPoint::__construct, ID:3(9,8)2

ColoredPoint $cp = ID:3(9,8)2

Inside ColoredPoint::serialize, ID:3(9,8)2


Inside Point::serialize, ID:3(9,8)2

string(100) "C:12:"ColoredPoint":75:{a:2:{s:5:"color";i:2;s:8:"baseData";s:30:"a:2:{s:1:"y";i:8;s:1:"x";i:9;}";}}"

Inside Point::unserialize, ID:4(9,8)2


Inside ColoredPoint::unserialize, ID:4(9,8)2

object(ColoredPoint)#4 (4) {
  ["color":"ColoredPoint":private]=>
  int(2)
  ["x":"Point":private]=>
  int(9)
  ["y":"Point":private]=>
  int(8)
  ["id":"Point":private]=>
  int(4)
}
---------------- end -------------------
