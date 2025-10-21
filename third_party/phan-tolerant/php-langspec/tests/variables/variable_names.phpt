--TEST--
PHP Spec test generated from ./variables/variable_names.php
--FILE--
<?php

/*
   +-------------------------------------------------------------+
   | Copyright (c) 2014 Facebook, Inc. (http://www.facebook.com) |
   +-------------------------------------------------------------+
*/

error_reporting(-1);

$v = 10;
$$v = 99;
var_dump($$v);
${$v} = 100;
var_dump(${$v});
${10} = 101;
var_dump(${10});

${1.2} = 102;
${'abc'} = 103;
${TRUE} = 104;
${FALSE} = 105;
${NULL} = 106;

${total} = 1000;		// allowed after warning: Use of undefined constant total - assumed 'total'
//${t o tal} = 1000;	// disallowed; ill-formed expression
//${+} = 1000;			// disallowed; ill-formed expression
${10 + 4} = 1000;		// allowed
${'ab' . 'xy'} = 1000;	// allowed

function f1 () { return 2.5; }

${1 + f1()} = 1000;		// allowed

function print_globals() {
  $globals = array();
  foreach ($GLOBALS as $k => $v) {
    if ($k != 'GLOBALS' &&
        $k != 'php_errormsg' &&
        $k != 'HTTP_RAW_POST_DATA' &&
        (!$k || $k[0] != '_')) {
      $globals[$k] = $v;
    }
  }
  asort($globals);
  var_dump($globals);
}
print_globals();
--EXPECTF--
int(99)
int(100)
int(101)

Notice: Use of undefined constant total - assumed 'total' in %s/variables/variable_names.php on line 25
array(12) {
  ["argc"]=>
  int(1)
  ["v"]=>
  int(10)
  [10]=>
  int(101)
  ["1.2"]=>
  int(102)
  ["abc"]=>
  int(103)
  [1]=>
  int(104)
  [""]=>
  int(106)
  ["total"]=>
  int(1000)
  [14]=>
  int(1000)
  ["abxy"]=>
  int(1000)
  ["3.5"]=>
  int(1000)
  ["argv"]=>
  array(1) {
    [0]=>
    string(%d) "%s/tests/variables/variable_names.php"
  }
}
