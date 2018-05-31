<?php

/**
 * @param array<string,string> $x
 */
function example_array_push(array $x) {
    array_push($x, new stdClass(), 2);
    // var_export($x);  // 'x' is preserved
    echo strlen($x);
}
example_array_push(['x' => 'y']);

/**
 * @param array<string,string> $x
 */
function example_array_unshift(array $x) {
    array_unshift($x, new stdClass(), 2);
    // var_export($x);  // 'x' is preserved (integers wouldn't be)
    echo strlen($x);
}
example_array_unshift(['x' => 'y']);

function example_array_pop() {
    $x = [2, 3];
    array_pop($x);
    echo strlen($x);  // TODO: Could be more specific, but only when 100% sure of the type
}

function example_array_shift() {
    $x = [2, 3];
    array_pop($x);
    echo strlen($x);  // TODO: Could be more specific, but only when 100% sure of the type
}

function example_array_splice() {
    $x = ['x' => 'y', 'y' => 'z'];
    array_splice($x, 1, 1, [new stdClass(), 42]);

    echo strlen($x);
    var_export($x);
}
