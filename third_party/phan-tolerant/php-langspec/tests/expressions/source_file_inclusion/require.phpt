--TEST--
PHP Spec test generated from ./expressions/source_file_inclusion/require.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

echo "Inside file >" . __FILE__ . "< at line >" . __LINE__ .
	"< with namespace >" . __NAMESPACE__ . "<\n";

//var_dump(MY_MIN);
//var_dump(MY_MAX);

// Try to require a non-existant file

$fileName = 'unknown.inc';
//$inc = require $fileName;
//echo "require file " . ($inc == 1 ? "does" : "does not") . " exist\n";

// require an existing file that has its own namespace

$fileName = 'limits' . '.inc';
$inc = require $fileName;
var_dump($inc);

echo "Inside file >" . __FILE__ . "< at line >" . __LINE__ .
	"< with namespace >" . __NAMESPACE__ . "<\n";

// require another existing file that has its own namespace
	
$inc = require('mycolors.inc');
var_dump($inc);

echo "Inside file >" . __FILE__ . "< at line >" . __LINE__ .
	"< with namespace >" . __NAMESPACE__ . "<\n";

echo "----------------------------------\n";

// Try to access constants defined in an included file

if (defined("MY_MIN"))
	echo "MY_MIN is defined with value >" . constant("MY_MIN") . "\n";
else
	echo "MY_MIN is not defined\n";

echo "----------------------------------\n";

// require a file that has no return statement

$inc = require('return_none.inc');
var_dump($inc);

// require a file that has a return statement without a return value

$inc = require('return_without_value.inc');
var_dump($inc);

// require a file that has a return statement with a return value

$inc = require('return_with_value.inc');
var_dump($inc);

echo "----------------------------------\n";

// see how low the precedence of require is

//if (require('return_with_value.inc') == 987) ;
if ((require('return_with_value.inc')) == 987) ;
//if (require('return_with_value.inc') | 987) ;
if ((require('return_with_value.inc')) | 987) ;
//if (require('return_with_value.inc') && 987) ;
if ((require('return_with_value.inc')) && 987) ;
//if (require('return_with_value.inc') or 987) ;
if ((require('return_with_value.inc')) or 987) ;

echo "----------------------------------\n";

// see if included file can access including file's variables, and if including file
// can access the included file's functions and variables

$v1 = 10;
$v2 = "Hello";
echo "Inside file >" . __FILE__ . "< at line >" . __LINE__ . "<\n";

echo "----------------------------------\n";

$inc = require 'test.inc';
var_dump($inc);

echo "----------------------------------\n";

echo "Inside file >" . __FILE__ . "< at line >" . __LINE__ . "<\n";
test();
echo "\$local1: $local1\n";

echo "Inside file >" . __FILE__ . "< at line >" . __LINE__ . "<\n";

echo "----------------------------------\n";

// get the set of included files

print_r(get_included_files());
--EXPECTF--
Inside file >%s/expressions/source_file_inclusion/require.php< at line >11< with namespace ><
================= xxx =================
Inside file >%s/expressions/source_file_inclusion/limits.inc< at line >14< with namespace >MyInclude<
int(1)
Inside file >%s/expressions/source_file_inclusion/require.php< at line >29< with namespace ><
Inside file >%s/expressions/source_file_inclusion/mycolors.inc< at line >13< with namespace >MyColors<
int(1)
Inside file >%s/expressions/source_file_inclusion/require.php< at line >37< with namespace ><
----------------------------------
MY_MIN is not defined
----------------------------------
int(1)
NULL
int(987)
----------------------------------
----------------------------------
Inside file >%s/expressions/source_file_inclusion/require.php< at line >86<
----------------------------------
int(100)
====
Array
(
    [0] => %s/expressions/source_file_inclusion/require.php
    [1] => %s/expressions/source_file_inclusion/limits.inc
    [2] => %s/expressions/source_file_inclusion/mycolors.inc
    [3] => %s/expressions/source_file_inclusion/return_none.inc
    [4] => %s/expressions/source_file_inclusion/return_without_value.inc
    [5] => %s/expressions/source_file_inclusion/return_with_value.inc
    [6] => %s/expressions/source_file_inclusion/test.inc
)
====
int(1)
----------------------------------
Inside file >%s/expressions/source_file_inclusion/require.php< at line >95<
Inside test() in %s/expressions/source_file_inclusion/test.inc

Notice: Undefined variable: v1 in %s/expressions/source_file_inclusion/test.inc on line 14

Notice: Undefined variable: v2 in %s/expressions/source_file_inclusion/test.inc on line 14
$v1: , $v2: 
$local1: 100
Inside file >%s/expressions/source_file_inclusion/require.php< at line >99<
----------------------------------
Array
(
    [0] => %s/expressions/source_file_inclusion/require.php
    [1] => %s/expressions/source_file_inclusion/limits.inc
    [2] => %s/expressions/source_file_inclusion/mycolors.inc
    [3] => %s/expressions/source_file_inclusion/return_none.inc
    [4] => %s/expressions/source_file_inclusion/return_without_value.inc
    [5] => %s/expressions/source_file_inclusion/return_with_value.inc
    [6] => %s/expressions/source_file_inclusion/test.inc
)
