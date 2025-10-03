<?php

/* @phan-file-suppress PhanUnreferencedFunction */

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

function test_match_unreachable($x) {
    match($x) {};  // no-op, should warn
    echo "Unreachable\n";
}
function test_match_reachable($x) {
    match($x) {default => 2};  // no-op, should warn
    echo "Reachable\n";
}
