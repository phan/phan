--TEST--
PHP Spec test generated from ./expressions/source_file_inclusion/require_once.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

// Try to require a non-existant file

//$inc = require_once('XXPositions.inc');
//var_dump($inc);

echo "----------------------------------\n";

// require an existing file

$inc = require('Positions.inc');
//$inc = require_once('Positions.inc');
var_dump($inc);

// require an existing file. It doesn't matter if the first require was with/without
// _once; subsequent use of require_once returns true

$inc = require_once('Positions.inc');
var_dump($inc);

var_dump(Positions\LEFT);
var_dump(Positions\TOP);

echo "----------------------------------\n";

///*
// require Point.inc to get at the Point class type

$inc = require('Point.inc');
var_dump($inc);

$p1 = new Point(10,20);
//*/

echo "----------------------------------\n";

// require Circle.inc to get at the Circle class type, which in turn uses the Point type

$inc = require('Circle.inc');
var_dump($inc);

$p2 = new Point(5, 6);
$c1 = new Circle(9, 7, 2.4);

echo "----------------------------------\n";

// get the set of required files

print_r(get_required_files());
--EXPECTF--
----------------------------------
int(1)
bool(true)
int(3)
int(1)
----------------------------------
int(1)
----------------------------------
int(1)
----------------------------------
Array
(
    [0] => %s/expressions/source_file_inclusion/require_once.php
    [1] => %s/expressions/source_file_inclusion/Positions.inc
    [2] => %s/expressions/source_file_inclusion/Point.inc
    [3] => %s/expressions/source_file_inclusion/Circle.inc
)
