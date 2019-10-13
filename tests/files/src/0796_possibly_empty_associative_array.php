<?php
/**
 * @return ?list<stdClass>
 */
function test_associative_array_possibly_empty(int $i, int $j) : ?array {
    $x = [];
    $x[$i] = new stdClass();
    unset($x[$j]);
    '@phan-debug-var $x';  // should not infer non-empty as a result of the unset statement.
    if ($x) {
        return $x;  // warns about returning associative-array instead of list
    }
    return null;
}
