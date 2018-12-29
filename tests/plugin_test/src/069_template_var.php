<?php

/**
 * @template T
 * @var T $x
 * @param T $y
 * @return T
 */
function test_var_creation($y) {
    // Some unanalyzable code that creates $x
    $a = substr('xtra', 0, 1);
    $$a = $y;  // TODO: Should detect the usage of $a
    echo strlen($x);  // should warn about misusing T
    return [$x];  // should warn about returning [T] instead of T
}
test_var_creation(new stdClass());
