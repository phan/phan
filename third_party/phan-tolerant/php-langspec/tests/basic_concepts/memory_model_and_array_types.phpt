--TEST--
PHP Spec test generated from ./basic_concepts/memory_model_and_array_types.php
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

///*
echo "----------------- simple assignment of array types ----------------------\n";

$x = 123;
$a = array(10, &$x, 'B' => new Point(1, 3));

echo "After '\$a = array(...)', \$a is "; var_dump($a);

$b = $a;

echo "After '\$b = \$a', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

++$b[0];

echo "After '++\$b[0]', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

$a[0] = 99;

echo "After '\$a[0] = 99', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

--$x;

echo "After '--\$x', \$a is "; var_dump($a);
echo "\$b is "; var_dump($b);

unset($a);
echo "After 'unset(\$a)', \$a is undefined, \$b is "; var_dump($b);
unset($b);
echo "After 'unset(\$b)', \$b is undefined\n";
//*/

///*
echo "----------------- byRef assignment of array types ----------------------\n";

$x = 123;
$a = array(10, &$x, 'B' => new Point(1, 3));

echo "After '\$a = array(...)', \$a is "; var_dump($a);

$c =& $a;

echo "After '\$c =& \$a', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

++$c[0];

echo "After '++\$c[0]', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

$a[0] = 99;

echo "After '\$a[0] = 99', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

--$x;

echo "After '--\$x', \$a is "; var_dump($a);
echo "\$c is "; var_dump($c);

unset($a);
echo "After 'unset(\$a)', \$a is undefined, \$c is "; var_dump($c);

unset($c);
echo "End\n";
//*/

///*
echo "----------------- unsetting array elements ----------------------\n";

$x = 123;
$a = array(10, 'M' => TRUE, &$x, 'B' => new Point(1, 3));

echo "at start, \$x is $x, \$a is "; var_dump($a);

unset($a[0]);
echo "after unset(\$a[0]), \$x is $x, \$a is "; var_dump($a);

unset($a['M']);
echo "after unset(\$a['M']), \$x is $x, \$a is "; var_dump($a);

unset($a[1]);
echo "after unset(\$a[1]), \$x is $x, \$a is "; var_dump($a);

//unset($a['B']);
//echo "after unset(\$a['B']), \$x is $x, \$a is "; var_dump($a);

unset($a);
echo "after unset(\$a), \$x is $x, \$a is undefined\n";
//*/
--EXPECT--
----------------- simple assignment of array types ----------------------

Inside Point::__construct, (1,3), point count = 1

After '$a = array(...)', $a is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '$b = $a', $a is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$b is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '++$b[0]', $a is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$b is array(3) {
  [0]=>
  int(11)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '$a[0] = 99', $a is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$b is array(3) {
  [0]=>
  int(11)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '--$x', $a is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(122)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$b is array(3) {
  [0]=>
  int(11)
  [1]=>
  &int(122)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After 'unset($a)', $a is undefined, $b is array(3) {
  [0]=>
  int(11)
  [1]=>
  &int(122)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}

Inside Point::__destruct, (1,3), point count = 0

After 'unset($b)', $b is undefined
----------------- byRef assignment of array types ----------------------

Inside Point::__construct, (1,3), point count = 1

After '$a = array(...)', $a is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '$c =& $a', $a is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$c is array(3) {
  [0]=>
  int(10)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '++$c[0]', $a is array(3) {
  [0]=>
  int(11)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$c is array(3) {
  [0]=>
  int(11)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '$a[0] = 99', $a is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$c is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After '--$x', $a is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(122)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
$c is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(122)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
After 'unset($a)', $a is undefined, $c is array(3) {
  [0]=>
  int(99)
  [1]=>
  &int(122)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}

Inside Point::__destruct, (1,3), point count = 0

End
----------------- unsetting array elements ----------------------

Inside Point::__construct, (1,3), point count = 1

at start, $x is 123, $a is array(4) {
  [0]=>
  int(10)
  ["M"]=>
  bool(true)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
after unset($a[0]), $x is 123, $a is array(3) {
  ["M"]=>
  bool(true)
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
after unset($a['M']), $x is 123, $a is array(2) {
  [1]=>
  &int(123)
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}
after unset($a[1]), $x is 123, $a is array(1) {
  ["B"]=>
  object(Point)#1 (2) {
    ["x":"Point":private]=>
    int(1)
    ["y":"Point":private]=>
    int(3)
  }
}

Inside Point::__destruct, (1,3), point count = 0

after unset($a), $x is 123, $a is undefined
