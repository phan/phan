<?php

/**
 * @return non-empty-array
 */
function bad786(array $in, string $key) {
    switch (rand() % 5) {
        case 0:
            return [];
        case 1:
            return $in;  // don't warn to minimize false positives.
        case 2:
            return [$key => $in];  // don't warn to minimize false positives.
        case 3:
            return [$key];
        default:
            return null;
    }
}
/**
 * @param non-empty-array<int,string> $values
 * @param non-empty-array<string,string> $p2 should warn about default
 */
function accept_non_empty_array($values, $p2 = []) {
    var_export([$p2]);
    foreach ($values as $v) {
        var_export($v);
    }
}
// should warn
accept_non_empty_array([], []);
// should not warn
accept_non_empty_array(['first'], ['key' => 'value']);
// should warn about first arg
accept_non_empty_array(['first' => 'x']);
/**
 * @param non-empty-array<string|stdClass> $values
 */
function accept_non_empty_array2(array $values) {
    echo count($values);
    foreach ($values as $k => $v) {
        echo intdiv($v, 2);
        echo spl_object_id($k);
    }
}
accept_non_empty_array2(['x']);
accept_non_empty_array2([$argv[0] => new stdClass()]);
accept_non_empty_array2([]);
