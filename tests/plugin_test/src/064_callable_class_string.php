<?php

/**
 * @param callable-string $x
 * @return callable-string
 */
function test_callable_string($x) {
    var_export($x('a string'));
    var_export($x($x));
    var_export(new $x());
    var_export(strlen($x));
    var_export(intdiv($x, 2));  // callable-string cannot cast to string
    return 'strlen';  // Phan doesn't check this yet, but shouldn't warn.
}

test_callable_string('strlen');
test_callable_string('stdClass');  // Phan should warn about this
test_callable_string('');  // Phan should warn about this
test_callable_string(function () {});  // Phan should warn about this
var_export(new ReflectionClass('invalid class name'));
var_export(new ReflectionClass('Some\MissingClass'));
var_export(new ReflectionClass('stdClass'));
var_export(new ReflectionClass(''));
// TODO: Should warn if passing in 'class::method' to ReflectionFunction
var_export(new ReflectionFunction(''));
var_export(new ReflectionFunction('strlen'));
var_export(new ReflectionFunction('an invalid function name'));
test_callable_string(2);

/**
 * @param class-string $x
 * @return class-string
 */
function test_class_string($x) {
    var_export(new $x());
    var_export($x($x));  // should warn
    var_export(strlen($x));
    var_export(intdiv($x, 2));  // class-string cannot cast to string
    return stdClass::class;  // Phan doesn't check this yet, but shouldn't warn.
}

test_class_string('strlen');
test_class_string('stdClass');  // Phan should warn about this
test_class_string('');  // Phan should warn about this
test_class_string(' ');  // Phan should warn about this
test_class_string('Phan should warn about this');

function testCast(string $a, string $b) {
    // should not warn
    test_callable_string($a);
    test_class_string($b);
}
