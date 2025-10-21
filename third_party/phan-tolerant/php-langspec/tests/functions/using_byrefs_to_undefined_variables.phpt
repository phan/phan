--TEST--
PHP Spec test generated from ./functions/using_byrefs_to_undefined_variables.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

///*

// take an undefined parameter passed byRef, and pass it byRef where
// the underlying value is modified. This causes the previously undefined variable
// to now be defined.

function f(&$p)
{
   echo '$p '.(isset($p) ? "is set\n" : "is not set\n");
   g($p);
   echo '$p '.(isset($p) ? "is set\n" : "is not set\n");
   var_dump($p);
}

function g(&$q)
{
   echo '$q '.(isset($q) ? "is set\n" : "is not set\n");
   $q = -10;
}

f();

var_dump($x);
f($x);           // non-existant variable going in
var_dump($x);

$a = array(10, 20, 30);
var_dump($a);

f($a[0]);
var_dump($a);

f($a[5]);      // non-existant element going in
var_dump($a);

f($a["red"]);  // non-existant element going in
var_dump($a);
//*/
///*

// take an undefined parameter passed byRef and assign it byRef. This
// causes the previously undefined variable to now be defined with a value of NULL.

function h(&$p)
{
   echo '$p '.(isset($p) ? "is set\n" : "is not set\n");
   $b = &$p;
   echo '$p '.(isset($p) ? "is set\n" : "is not set\n");
   var_dump($p);
   var_dump($b);
}

h();

var_dump($x);
h($x);           // non-existant variable going in
var_dump($x);

$a = array(10, 20, 30);
var_dump($a);

h($a[0]);
var_dump($a);

h($a[5]);      // non-existant element going in
var_dump($a);

h($a["red"]);  // non-existant element going in
var_dump($a);
//*/
///*

// take an undefined parameter passed byRef and return it byRef. This
// causes the previously undefined variable to now be defined with a value of NULL.

function &k(&$p)
{
   echo '$p '.(isset($p) ? "is set\n" : "is not set\n");
   var_dump($p);
   return $p;  // return undefined variable
}

$a = array(10, 20, 30);
var_dump($a);

$d = &k($a[0]);
var_dump($d);
var_dump($a);

$d = &k($a[5]);      // non-existant element going in
var_dump($d);
var_dump($a);

$d = &k($a["red"]);  // non-existant element going in
var_dump($d);
var_dump($a);
//*/

///*

// returning literals byRef is okay

function &m1()
{
   return NULL;
}

$d = &m1();
var_dump($d);

function &m2()
{
   return 1234;
}

$d = &m2();
var_dump($d);
//*/
--EXPECTF--
Warning: %s
$p is not set
$q is not set
$p is set
int(-10)

Notice: Undefined variable: x in %s/functions/using_byrefs_to_undefined_variables.php on line 33
NULL
$p is not set
$q is not set
$p is set
int(-10)
int(-10)
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
$p is set
$q is set
$p is set
int(-10)
array(3) {
  [0]=>
  int(-10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
$p is not set
$q is not set
$p is set
int(-10)
array(4) {
  [0]=>
  int(-10)
  [1]=>
  int(20)
  [2]=>
  int(30)
  [5]=>
  int(-10)
}
$p is not set
$q is not set
$p is set
int(-10)
array(5) {
  [0]=>
  int(-10)
  [1]=>
  int(20)
  [2]=>
  int(30)
  [5]=>
  int(-10)
  ["red"]=>
  int(-10)
}

Warning: %s
$p is not set
$p is not set
NULL
NULL
int(-10)
$p is set
$p is set
int(-10)
int(-10)
int(-10)
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
$p is set
$p is set
int(10)
int(10)
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
$p is not set
$p is not set
NULL
NULL
array(4) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
  [5]=>
  NULL
}
$p is not set
$p is not set
NULL
NULL
array(5) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
  [5]=>
  NULL
  ["red"]=>
  NULL
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
$p is set
int(10)
int(10)
array(3) {
  [0]=>
  &int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
$p is not set
NULL
NULL
array(4) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
  [5]=>
  &NULL
}
$p is not set
NULL
NULL
array(5) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
  [5]=>
  NULL
  ["red"]=>
  &NULL
}
%ANULL
%Aint(1234)
