--TEST--
PHP Spec test generated from ./basic_concepts/storage_duration.php
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
  private static $pointCount = 0;

  private $x;
  private $y;

	public static function getPointCount()
	{
		return self::$pointCount;
	}

	public function __construct($x = 0, $y = 0)
	{
		$this->x = $x;
		$this->y = $y;
		++self::$pointCount;

    echo "\nInside " . __METHOD__ . ", $this, point count = " . self::$pointCount . "\n\n";
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

	public function __destruct()
	{
		--self::$pointCount;

    echo "\nInside " . __METHOD__ . ", $this, point count = " . self::$pointCount . "\n\n";
  }
///*
	public function __clone()
	{
		++self::$pointCount;

    echo "\nInside " . __METHOD__ . ", $this, point count = " . self::$pointCount . "\n\n";
  }
//*/

	public function __toString()
	{
		return '(' . $this->x . ',' . $this->y . ')';
	}	
}

echo "---------------- start -------------------\n";

$av1 = new Point(0, 1);

echo "---------------- after \$av1 init -------------------\n";

static $sv1 = TRUE;

echo "---------------- after \$sv1 decl -------------------\n";

$sv1 = new Point(0, 2);

echo "---------------- after \$sv1 init -------------------\n";

function doit($p1)
{
  echo "---------------- Inside function_A -------------------\n";

  $av2 = new Point(1, 1);

  echo "---------------- after \$av2 init -------------------\n";

  static $sv2 = 0;

  echo "---------------- after \$sv2 decl -------------------\n";

  $sv2 = new Point(1, 2);

  echo "---------------- after \$sv2 init -------------------\n";

  if ($p1)
  {
    echo "---------------- Inside if TRUE -------------------\n";

    $av3 = new Point(2, 1);

    echo "---------------- after \$av3 init -------------------\n";

    static $sv3 = NULL;

    echo "---------------- after \$sv3 decl -------------------\n";

    $sv3 = new Point(2, 2);

    echo "---------------- after \$sv3 init -------------------\n";
    // ...
  }

  $av1 = new Point(2, 3);

  // Order of destruction is implementation-defined
  echo "---------------- after \$av1 reinit -------------------\n";
}

doit(TRUE);

echo "---------------- after call to func -------------------\n";

function factorial($i)
{
  if ($i > 1) return $i * factorial($i - 1);
  else if ($i == 1) return $i;
  else return 0;
}

$count = 10;
$result = factorial($count);
echo "\$result = $result\n";

echo "---------------- end -------------------\n";
--EXPECTF--
---------------- start -------------------

Inside Point::__construct, (0,1), point count = 1

---------------- after $av1 init -------------------
---------------- after $sv1 decl -------------------

Inside Point::__construct, (0,2), point count = 2

---------------- after $sv1 init -------------------
---------------- Inside function_A -------------------

Inside Point::__construct, (1,1), point count = 3

---------------- after $av2 init -------------------
---------------- after $sv2 decl -------------------

Inside Point::__construct, (1,2), point count = 4

---------------- after $sv2 init -------------------
---------------- Inside if TRUE -------------------

Inside Point::__construct, (2,1), point count = 5

---------------- after $av3 init -------------------
---------------- after $sv3 decl -------------------

Inside Point::__construct, (2,2), point count = 6

---------------- after $sv3 init -------------------

Inside Point::__construct, (2,3), point count = 7

---------------- after $av1 reinit -------------------

Inside Point::__destruct, (%d,%d), point count = 6


Inside Point::__destruct, (%d,%d), point count = 5


Inside Point::__destruct, (%d,%d), point count = 4

---------------- after call to func -------------------
$result = 3628800
---------------- end -------------------

Inside Point::__destruct, (%d,%d), point count = 3


Inside Point::__destruct, (%d,%d), point count = 2


Inside Point::__destruct, (%d,%d), point count = 1


Inside Point::__destruct, (%d,%d), point count = 0

