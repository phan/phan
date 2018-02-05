<?php

/**
 * @param array<int, string> $a
 * @param array<string, string> $b
 */
function test402(array $a, array $b, int $num, string $str) {
    $x = array_search($num, $a);
    $y = array_search($str, $a);  // valid
    echo strlen($y);  // should warn, is of type int|false

    $x2 = array_search($num, $b);  // invalid
    $y2 = array_search($str, $b);  // valid
    echo intdiv($y2, 2);  // should warn, is of type string|false
    return [$x, $y, $x2, $y2];
}

/**
 * @param array<int, string> $a
 * @param array<string, string> $b
 */
function test402Key(array $a, array $b) {
    echo strlen(key($a));  // should warn, key is int|false
    echo intdiv(key($b), 2);  // should warn, key is int|false
}
