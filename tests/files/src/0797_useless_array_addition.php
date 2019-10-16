<?php

function test_assign_op($var) {
    $arr = [$var];
    $arr += [$var + 1];
    return $arr;
}
function test_binary_op($var) {
    $arr = [$var];
    return $arr + [$var + 1];
}
function test_binary_op_list($var, ...$args) {
    var_export([$var] + $args);
    var_export($args + [$var]);
    var_export(array_merge($args, [2]) + $args);
}

const DEFAULTS797 = ['timeout' => 1];

function test_assign_op_string(string $var) : array {
    $fields = ['timeout' => 2, 'other' => $var];
    $fields += DEFAULTS797;
    return $fields;
}
