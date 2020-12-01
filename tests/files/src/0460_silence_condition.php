<?php

/**
 * @param string|array $x
 * @param string|array $y
 *
 * The error control operator is unnecessary here
 */
function silence460($x, $y) {
    '@phan-debug-var $x';
    if (@is_string($x)) {
        '@phan-debug-var $x';
        echo count($x);
    }
    if (@!is_array($y)) {
        echo count($y);
    }
}
