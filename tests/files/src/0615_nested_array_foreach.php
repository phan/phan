<?php

declare(strict_types=1);

function test_nested_array_foreach() {
    $array = [
        [
            ['a', 'b'],
            ['x', 'y'],
        ],
    ];

    foreach ($array as $key => [[$_1, $_2], [$_3, $_4]]) {
        echo "{$key}: {$_1}, {$_2}, {$_3}, {$_4}", PHP_EOL;
    }
}

function test_bad_nested_array_foreach(stdClass $o) {
    $badArray = [
        [
            ['a', $o],
            ['x', 'y'],
        ],
    ];
    foreach ($badArray as $key => [[$_1, $_2], [$_3, $_4]]) {
        echo count($key);  // this warns, $key is an int
        echo $_1;
        echo $_2;  // this warns, this is an stdClass
        echo $_3;
        echo $_4;
    }
}
