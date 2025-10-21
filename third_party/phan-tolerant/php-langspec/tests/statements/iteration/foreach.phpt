--TEST--
PHP Spec test generated from ./statements/iteration/foreach.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$array = array();
foreach ($array as $a)
{
    echo $a."\n";
}

$colors = array("red", "white", "blue");

// access each element's value

foreach ($colors as $color)  :
    echo $color."\n";
endforeach  ;
echo $color."\n";

// access each element's value anf its element number


foreach ($colors as $index => $color)
{
    echo "Index: $index; Color: $color\n";
    var_dump($index);
}
echo "Index: $index; Color: $color\n";

// Modify the local copy of an element's value

foreach ($colors as $color)
{
    echo $color."\n";
	$color = "black";
    echo $color."\n";
}
var_dump($colors);

// Modify the the actual element itself

foreach ($colors as &  $color)  // note the &
{
    echo $color."\n";
	$color = "black";
    echo $color."\n";
}
var_dump($colors);

foreach ($colors as $index => & $color)
{
    echo $color."\n";
	$color = "green";
    echo $color."\n";
}
var_dump($colors);

$ary = array();
$ary[0][0] = "abc";
$ary[0][1] = "ij";
$ary[1][0] = "mnop";
$ary[1][1] = "xyz";

foreach ($ary as $e1)
{
    foreach ($e1 as $e2)
    {
        echo "  $e2";
    }
    echo "\n";
}

// test use of list

$a = array(array(10,20), array(1.2, 4.5), array(TRUE, "abc"));
foreach ($a as $key => $value)
{
    echo "------\n";
    var_dump($key);
    var_dump($value);
}

foreach ($a as $key => list($v1, $v2))
{
    echo "------\n";
    var_dump($key);
    var_dump($v1);
    var_dump($v2);
}
--EXPECT--
red
white
blue
blue
Index: 0; Color: red
int(0)
Index: 1; Color: white
int(1)
Index: 2; Color: blue
int(2)
Index: 2; Color: blue
red
black
white
black
blue
black
array(3) {
  [0]=>
  string(3) "red"
  [1]=>
  string(5) "white"
  [2]=>
  string(4) "blue"
}
red
black
white
black
blue
black
array(3) {
  [0]=>
  string(5) "black"
  [1]=>
  string(5) "black"
  [2]=>
  &string(5) "black"
}
black
green
black
green
black
green
array(3) {
  [0]=>
  string(5) "green"
  [1]=>
  string(5) "green"
  [2]=>
  &string(5) "green"
}
  abc  ij
  mnop  xyz
------
int(0)
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(20)
}
------
int(1)
array(2) {
  [0]=>
  float(1.2)
  [1]=>
  float(4.5)
}
------
int(2)
array(2) {
  [0]=>
  bool(true)
  [1]=>
  string(3) "abc"
}
------
int(0)
int(10)
int(20)
------
int(1)
float(1.2)
float(4.5)
------
int(2)
bool(true)
string(3) "abc"
