<?php

/**
 * @param ?int|?string $x
 * @param ?int|?string $y
 */
function negated_or342($x, $y) {
    if (!(is_int($x) || $x === null)) {
        echo intdiv($x, 2);  // warn, it's a string
        echo strlen($x);
    }

    if (!(is_int($y) || is_string($y))) {
        echo intdiv($y, 2);  // warn, it's null
    }
}
