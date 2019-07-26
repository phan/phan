<?php

declare(strict_types=1);

/**
 * @param array<int,array<int,array<int,string>>> $array
 */
function test_nested_array_foreach_noshape(array $array) {
    foreach ($array as $key => list(list($_1, $_2), list($_3, $_4), $_5)) {
        echo "{$key}: {$_1}, {$_2}, {$_3}, {$_4}", PHP_EOL;
        // Should infer string for all of these:
        echo intdiv($_1, $_2);
        echo intdiv($_3, $_4);
        echo strlen($_5);  // array<int,string>
    }
}
/**
 * @param array<int,array<int,array<int,string>>> $array
 */
function test_nested_array_foreach_complex(array $array) {
    $newArray = [];
    foreach ($array as $key => $newArray[]) {
        echo strlen($key);  // should infer array
        echo strlen($newArray);  // should infer array<int,array<int,string>> - TODO: Verify array iteration in PHP interpreter
    }
}
