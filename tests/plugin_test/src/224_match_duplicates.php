<?php

function test_duplicates(int $key, int $other): int|string {
    return match($key) {
        1, 2, 2 => 'x',
        '1' => 'y',
        __LINE__ => 'z',
        1 + 1 => 'w',
        $other => 1,
        $other => 2,
        null, null => 3,
    };
}
