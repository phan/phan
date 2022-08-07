<?php
function test_true(true $x): true {
    '@phan-debug-var $x';
    return $x;
}
function test_nullabletrue(true|null $x): ?true {
    '@phan-debug-var $x';
    return $x;
}
function test_false(false $x): false {
    '@phan-debug-var $x';
    return $x;
}
function test_null(null $x): null {
    '@phan-debug-var $x';
    return $x;
}
var_export([
    test_true(true),
    test_true(null),
    test_nullabletrue(true),
    test_nullabletrue(false),
    test_nullabletrue(null),
    test_false(false),
    test_false(true),
    test_null(false),
    test_null(null),
]);
