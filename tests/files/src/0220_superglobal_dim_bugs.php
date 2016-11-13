<?php

if (isset($_GET['a'])) {
    $_GET['b'] = $_GET['a'];
}
$GLOBALS['c'] = $_GET['a'];
var_dump($b);  // $_GET does not create globals.
var_dump($c);  // $_GLOBALS['c'] does create globals.

function foo() {
    global $e;
    $GLOBALS['d'] = 'value';
    $GLOBALS['e'] = 'v2';
    var_dump($d);  // Error, didn't declare `global $d` in the function.
    var_dump($e);
}

array_merge([], $_GET);
foo();
