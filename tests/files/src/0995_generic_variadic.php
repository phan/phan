<?php

/**
 * @template T
 * @param T ...$values
 * @return T[]
 */
function list_to_array(...$values) {
    return $values;
}

/**
 * @template T
 * @param T ...$values
 * @return T
 */
function list_random(...$values) {
    if (!$values) {
        throw new Exception();
    }
    return $values[array_rand($values)];
}

// Normal cases
$a = list_to_array(1);
'@phan-debug-var $a';
$a = list_to_array(1, 2);
'@phan-debug-var $a';
$a = list_to_array(1, 'x');
'@phan-debug-var $a';
$a = list_to_array(rand(), random_bytes(1));
'@phan-debug-var $a';
$r = list_random(1);
'@phan-debug-var $r';
$r = list_random(1, 2);
'@phan-debug-var $r';
$r = list_random(1, 'x');
'@phan-debug-var $r';
$r = list_random(rand(), random_bytes(1));
'@phan-debug-var $r';

// When no parameters are given, the template type is resolved to 'never', which basically means this can't happen.
// We don't seem to deduce much based on that...

// An array of type `never[]` can only be an empty array (it can't hold values of any type).
$a = list_to_array();
'@phan-debug-var $a';
var_dump(...$a); // should emit error, 0 arguments
$a[] = 42; // should turn into `non-empty-list<42>`
'@phan-debug-var $a';

// A variable of type `never` can't exist (the function must not return normally).
$a = list_random();
'@phan-debug-var $a';
var_dump($a); // should emit error, we can't get here
