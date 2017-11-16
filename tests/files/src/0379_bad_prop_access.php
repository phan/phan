<?php
function main379(string $value) {
    $array = ['a' -> $value];  // Should be 'a' => $value
    $str = 'a';
    $str->$value = 33;  // Should warn about accessing property of a string.
    $pi = 3.14;
    $pi->value = 3.14159;  // should warn
    $e = 2.718;
    $e::$value = 2.71828;  // should warn
    $four = 4;
    $four->$value = 5;  // should warn about left hand side, even for dynamic property access

    $eleven = 11;
    echo $eleven::value . "\n";  // should warn
    return $array;
}

/**
 * @param object $x
 */
function test_object($x) {
    var_export($x[0]);  // invalid
    return $x->prop;
}

function test_null() {
    $x = null;
    return $x->prop;
}
