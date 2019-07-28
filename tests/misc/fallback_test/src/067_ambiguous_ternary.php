<?php

// When phan is using the polyfill parser (any php version), it should warn about deprecated ambiguous conditionals
function example67($a, $b, $c, $x, $y) {
    return [
        1 ? 0 : 1 ? 3 : 0,
        $a ? $b : $c ? $x : $y,
        1 ? 0 : 1 ?: 0,
        $a ? $b : $c ?: $x,
        $a ?: $b ? $c : $undefined,
        1 ?: 0 ? 1 : 0,

        // These have parentheses, and Phan should not warn
        ($a ? $b : $c) ? $x : $y,
        $a ? $b : ($c ? $x : $y),
        ($a ? $b : $c) ?: $x,
        ($a ?: $b) ? $c : $x,
        // The parsing order doesn't affect the behavior, so Phan should not warn.
        $a ?: $c ?: $x,
        ($a ?: $c) ?: $undefined,
    ];
}
// Add a syntax error to force this test to use the polyfill parser.
<
