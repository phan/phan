<?php
function test_contains(string $x) {
    return [
        str_contains('x', $x),
        str_starts_with('x', $x),
        str_ends_with('x', $x),
    ];
}
test_contains('xylophone');
