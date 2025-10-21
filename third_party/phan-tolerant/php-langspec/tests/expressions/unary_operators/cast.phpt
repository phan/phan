--TEST--
PHP Spec test generated from ./expressions/unary_operators/cast.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

///*
$v = 0;
echo var_dump((bool)$v);
echo var_dump((boolean)$v);
echo var_dump((int)$v);
echo var_dump((integer)$v);
echo var_dump((float)$v);
echo var_dump((double)$v);
echo var_dump((real)$v);
echo var_dump((string)$v);
echo var_dump((array)$v);
echo var_dump((object)$v);

echo var_dump((binary)$v);
echo var_dump((binary)"");
echo var_dump((binary)"abcdef");

echo var_dump($v);
echo var_dump((unset)$v);
echo var_dump($v);
//*/

///*
// Test all kinds of values to see which can be converted

$ary1 = array(5 => 10, 2 => 20);
$ary2 = array(1.23, TRUE, "Hello", NULL);

$scalarValueList = array(10, -100, 0, 1.56, 0.0, 1234e200, INF, -INF, NAN, TRUE, FALSE, NULL,
 "123", 'xx', "", "0", "00", "0.000", "0ABC", "0.000ABC", $ary1, $ary2);

foreach ($scalarValueList as $v)
{
	var_dump($v);

	echo "   "; var_dump((bool)$v);
	echo "   "; var_dump((bool)$v);
	echo "   "; var_dump((int)$v);
	echo "   "; var_dump((float)$v);
	echo "   "; var_dump((string)$v);
	echo "   "; var_dump((array)$v);
	echo "   >>---"; var_dump((object)$v);
}
//*/

///*
var_dump(10/3);
var_dump((int)(10/3));				// results in the int 3 rather than the float 3.333...
var_dump((array)(16.5));			// results in an array of 1 float; [0] = 16.5
var_dump((int)(float)"123.87E3");	// results in the int 123870
//*/

///*
echo "---------------\n";

class C {}		// has no __toString method

$c1 = new C;
var_dump($c1);
//var_dump((string)$c1);

class D
{
	public function __toString()
	{
		return "AAA";
	}
}

$d1 = new D;
var_dump($d1);
var_dump((string)$d1);
//*/

///*
echo "---------------\n";

class E
{
	const CON1 = 123;				// constants irrelevent for conversion purposes
	public function f() {}			// methods irrelevent for conversion purposes
	private static $fsprop = 0;		// static properties irrelevent for conversion purposes

	private $priv_prop;
	protected $prot_prop = 12.345;
	public $publ_prop;

	public function __construct($p1)
	{
		$this->publ_prop = $p1;
	}
}

$e1 = new E(array(10, 1.2, "xxx"));
echo var_dump((bool)$e1);		// bool(true)
//echo var_dump((int)$e1);		// invalid
//echo var_dump((float)$e1);		// invalid
//echo var_dump((string)$e1);	// only works if __toString() defined
$ary = (array)$e1;
echo var_dump($ary);			// array of zero or more elements, 1 per instance property
echo var_dump((object)$e1);		// redundant; OK

foreach ($ary as $key => $val)
{
	echo "\$key: >$key<, len: " . strlen($key) . ", \$val: >$val<\n";
}
//*/

///*
echo "---------------\n";

//$infile = fopen("Testfile.txt", 'r');
$infile = STDIN;
var_dump($infile);

echo var_dump((bool)$infile);
echo var_dump((int)$infile);
echo var_dump((float)$infile);
echo var_dump((string)$infile);
echo var_dump((array)$infile);
$v = (array)$infile;
var_dump($v[0]);
var_dump(gettype($v[0]));
echo var_dump((object)$infile);
//*/

echo "---------------\n";

$str = "AaBb123$%^";
$binStr = (binary)$str;
var_dump($binStr);

$binStr = b"AaBb123$%^";
var_dump($binStr);
--EXPECTF--
bool(false)
bool(false)
int(0)
int(0)
float(0)
float(0)
float(0)
string(1) "0"
array(1) {
  [0]=>
  int(0)
}
object(stdClass)#1 (1) {
  ["scalar"]=>
  int(0)
}
string(1) "0"
string(0) ""
string(6) "abcdef"
int(0)
NULL
int(0)
int(10)
   bool(true)
   bool(true)
   int(10)
   float(10)
   string(2) "10"
   array(1) {
  [0]=>
  int(10)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  int(10)
}
int(-100)
   bool(true)
   bool(true)
   int(-100)
   float(-100)
   string(4) "-100"
   array(1) {
  [0]=>
  int(-100)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  int(-100)
}
int(0)
   bool(false)
   bool(false)
   int(0)
   float(0)
   string(1) "0"
   array(1) {
  [0]=>
  int(0)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  int(0)
}
float(1.56)
   bool(true)
   bool(true)
   int(1)
   float(1.56)
   string(4) "1.56"
   array(1) {
  [0]=>
  float(1.56)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  float(1.56)
}
float(0)
   bool(false)
   bool(false)
   int(0)
   float(0)
   string(1) "0"
   array(1) {
  [0]=>
  float(0)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  float(0)
}
float(1.234E+203)
   bool(true)
   bool(true)
   int(0)
   float(1.234E+203)
   string(10) "1.234E+203"
   array(1) {
  [0]=>
  float(1.234E+203)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  float(1.234E+203)
}
float(INF)
   bool(true)
   bool(true)
   int(0)
   float(INF)
   string(3) "INF"
   array(1) {
  [0]=>
  float(INF)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  float(INF)
}
float(-INF)
   bool(true)
   bool(true)
   int(0)
   float(-INF)
   string(4) "-INF"
   array(1) {
  [0]=>
  float(-INF)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  float(-INF)
}
float(NAN)
   bool(true)
   bool(true)
   int(0)
   float(NAN)
   string(3) "NAN"
   array(1) {
  [0]=>
  float(NAN)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  float(NAN)
}
bool(true)
   bool(true)
   bool(true)
   int(1)
   float(1)
   string(1) "1"
   array(1) {
  [0]=>
  bool(true)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  bool(true)
}
bool(false)
   bool(false)
   bool(false)
   int(0)
   float(0)
   string(0) ""
   array(1) {
  [0]=>
  bool(false)
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  bool(false)
}
NULL
   bool(false)
   bool(false)
   int(0)
   float(0)
   string(0) ""
   array(0) {
}
   >>---object(stdClass)#1 (0) {
}
string(3) "123"
   bool(true)
   bool(true)
   int(123)
   float(123)
   string(3) "123"
   array(1) {
  [0]=>
  string(3) "123"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(3) "123"
}
string(2) "xx"
   bool(true)
   bool(true)
   int(0)
   float(0)
   string(2) "xx"
   array(1) {
  [0]=>
  string(2) "xx"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(2) "xx"
}
string(0) ""
   bool(false)
   bool(false)
   int(0)
   float(0)
   string(0) ""
   array(1) {
  [0]=>
  string(0) ""
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(0) ""
}
string(1) "0"
   bool(false)
   bool(false)
   int(0)
   float(0)
   string(1) "0"
   array(1) {
  [0]=>
  string(1) "0"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(1) "0"
}
string(2) "00"
   bool(true)
   bool(true)
   int(0)
   float(0)
   string(2) "00"
   array(1) {
  [0]=>
  string(2) "00"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(2) "00"
}
string(5) "0.000"
   bool(true)
   bool(true)
   int(0)
   float(0)
   string(5) "0.000"
   array(1) {
  [0]=>
  string(5) "0.000"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(5) "0.000"
}
string(4) "0ABC"
   bool(true)
   bool(true)
   int(0)
   float(0)
   string(4) "0ABC"
   array(1) {
  [0]=>
  string(4) "0ABC"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(4) "0ABC"
}
string(8) "0.000ABC"
   bool(true)
   bool(true)
   int(0)
   float(0)
   string(8) "0.000ABC"
   array(1) {
  [0]=>
  string(8) "0.000ABC"
}
   >>---object(stdClass)#1 (1) {
  ["scalar"]=>
  string(8) "0.000ABC"
}
array(2) {
  [5]=>
  int(10)
  [2]=>
  int(20)
}
   bool(true)
   bool(true)
   int(1)
   float(1)
   
Notice: Array to string conversion in %s/expressions/unary_operators/cast.php on line 50
string(5) "Array"
   array(2) {
  [5]=>
  int(10)
  [2]=>
  int(20)
}
   >>---object(stdClass)#1 (2) {
  [5]=>
  int(10)
  [2]=>
  int(20)
}
array(4) {
  [0]=>
  float(1.23)
  [1]=>
  bool(true)
  [2]=>
  string(5) "Hello"
  [3]=>
  NULL
}
   bool(true)
   bool(true)
   int(1)
   float(1)
   
Notice: Array to string conversion in %s/expressions/unary_operators/cast.php on line 50
string(5) "Array"
   array(4) {
  [0]=>
  float(1.23)
  [1]=>
  bool(true)
  [2]=>
  string(5) "Hello"
  [3]=>
  NULL
}
   >>---object(stdClass)#1 (4) {
  [0]=>
  float(1.23)
  [1]=>
  bool(true)
  [2]=>
  string(5) "Hello"
  [3]=>
  NULL
}
float(3.3333333333333)
int(3)
array(1) {
  [0]=>
  float(16.5)
}
int(123870)
---------------
object(C)#1 (0) {
}
object(D)#2 (0) {
}
string(3) "AAA"
---------------
bool(true)
array(3) {
  [" E priv_prop"]=>
  NULL
  [" * prot_prop"]=>
  float(12.345)
  ["publ_prop"]=>
  array(3) {
    [0]=>
    int(10)
    [1]=>
    float(1.2)
    [2]=>
    string(3) "xxx"
  }
}
object(E)#3 (3) {
  ["priv_prop":"E":private]=>
  NULL
  ["prot_prop":protected]=>
  float(12.345)
  ["publ_prop"]=>
  array(3) {
    [0]=>
    int(10)
    [1]=>
    float(1.2)
    [2]=>
    string(3) "xxx"
  }
}
$key: > E priv_prop<, len: 12, $val: ><
$key: > * prot_prop<, len: 12, $val: >12.345<

Notice: Array to string conversion in %s/expressions/unary_operators/cast.php on line 115
$key: >publ_prop<, len: 9, $val: >Array<
---------------
resource(1) of type (stream)
bool(true)
int(1)
float(1)
string(14) "Resource id #1"
array(1) {
  [0]=>
  resource(1) of type (stream)
}
resource(1) of type (stream)
string(8) "resource"
object(stdClass)#4 (1) {
  ["scalar"]=>
  resource(1) of type (stream)
}
---------------
string(10) "AaBb123$%^"
string(10) "AaBb123$%^"
