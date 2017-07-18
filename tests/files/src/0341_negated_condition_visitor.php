<?php

/**
 * @param int|string $x
 * @param int|string $y
 */
function testNegate341($x, $y) {
    if (!is_string($x)) {
        intdiv($x, 2);
    } else {
        $z = strlen($x);
    }

    if (!is_string($y)) {
        echo strlen($y);
    } else {
        echo intdiv($y, 2);
    }
}

function testNegate341B(?int $x) {
    if (!isset($x)) {
        intdiv($x, 2);  // wrong.
    } else if (rand() % 2 === 1) {
        // do nothing
    } else {
        echo strlen($x);  // should infer $x is int and warn
    }
}

function testNegate341C(?int $x) {
    if (empty($x)) {
        intdiv($x, 2);  // wrong if it's null, can be null or 0 (?int)
    } else {
        echo strlen($x);  // should infer $x is int and warn
    }
}

/**
 * @param int|string $x
 */
function testNegate341D($x) {
    return is_string($x)
        ? intdiv($x, 2)  // wrong, a string
        : strlen($x);  // wrong, an int
}

/**
 * @param int|string $x
 */
function testNegate341E($x) {
    is_string($x)
        ? intdiv($x, 2)  // wrong, a string
        : strlen($x);  // wrong, an int
}
