<?php

/**
 * @param array<string,string> $a
 * @param array<string,stdClass> $b
 * @return array<string,int>
 */
function array_map_multiple_test(array $a, array $b) {
    // Should warn about wrong params and return type
    return array_map(function(stdClass $x, string $y) : array {
        return [$x, $y];
    }, $a, $b);
}
array_map_multiple_test(['x' => 'y'], ['x' => new stdClass()]);
