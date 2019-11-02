<?php

const DEFAULT_SETTINGS = [
    'timeout' => 'blah',
    'blocking' => false,
];

function testArrayKeyExists(int $i, ...$others) {
    if (array_key_exists(-1, $others)) {
        echo "Lists start at -1\n";
    } elseif (array_key_exists('one', $others)) {
        echo "Lists start at one\n";
    } elseif (array_key_exists('-1', $others)) {
        echo "Lists start at -1\n";
    } elseif (array_key_exists(null, $others)) {
        echo "Lists start at null\n";
    } elseif (array_key_exists('0', $others)) {
        // should not warn
        echo "Has at least one element\n";
    }
    if (array_key_exists($i, DEFAULT_SETTINGS)) {
        echo "Impossible\n";
    }
}
