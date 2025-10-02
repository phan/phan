<?php
function test_match_unreachable($x) {
    match($x) {};  // no-op, should warn
    echo "Unreachable\n";
}
function test_match_reachable($x) {
    match($x) {default => 2};  // no-op, should warn
    echo "Reachable\n";
}
function test_match_value($x): int {
    if (rand(0, 1)) {
        return match($x) {
            true => 'x',
            $x => 'y',
        };
    } elseif (rand(0, 2)) {
        return match($x) {};  // might as well infer void unless 'never' is supported
    }
    return match($x) {
        default => null,
    };
}
