<?php
function test180($x) {
    return $x === 'a' ? true : false;
}
function test180b($x) {
    return $x > 0 ? false : true;
}
function test180c($x) {
    return $x > 0 ?: false;
}
function test180d($x) {
    return [isset($x) == true, empty($x) !== false, !$x xor false];
}
var_export([test180('a'), test180b(1), test180c(0), test180d(0)]);
