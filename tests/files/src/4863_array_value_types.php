<?php

/**
 * @param array<int,string> $bar
 */
function myFunc(array $bar): void {
    echo $bar[0];
}

/**
 * @param int[] $ints
 */
function buildAndCall(array $ints): void {
    $var = [];
    foreach ($ints as $val) {
        $var[$val] = new stdClass();
    }
    '@phan-debug-var $var';
    myFunc($var);
}

buildAndCall([1, 2, 3]);
