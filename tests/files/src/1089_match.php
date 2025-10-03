<?php

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
