<?php
function expect_array151(array $x) {
    var_export($x);
}
/**
 * @param mixed $b
 */
function test151($a, $b) {
    expect_array151(array_merge(array_values($a), $b));
    expect_array151(array_keys($a));
    expect_array151(array_keys($a, 'search', true));
    $keys = array_keys($a);
    if (is_string(key($keys))) {  // should warn
        echo "Impossible\n";
    }
    $values = array_values($a);
    $key_t = key($values);

    if (is_string(key($values))) {
        echo "Impossible\n";
    }
    if (key($a) === false) {
        echo "Impossible\n";
    }
}
test151(['abc'], ['xyz', 'def']);
