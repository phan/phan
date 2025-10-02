<?php
function arrow_function_use_return_value(int $initial, int $x) {
    // This doesn't modify $initial by reference, and there's no way to do that with short arrow syntax.
    $add = fn (int $v) => $initial += $v;
    // UseReturnValuePlugin should warn about not using the return value of $add.
    $add(2);
    $modify = fn (int &$v) => $v += $initial;
    $modify($x);  // should not warn about not using the return value.
    $modify(2);  // should warn about incorrect usage of short arrow functions

    return $x;
}
$modify2 = fn &(int &$v) => $v *= 2;  // this is not a valid reference.
$arg = 1;
var_export($modify2($arg));
var_export($modify2($arg));
$modify3 = fn &(int &$v) => 2;  // this is not a valid reference.
var_export($modify3($arg));
arrow_function_use_return_value(3, 4);
