<?php

/**
 * @param string &$x @phan-output-reference
 * @return void
 */
function foo383(&$x) {
    $x = 'value';
}

/**
 * @return void
 */
function main383() {
    $x = null;
    foo383($x);  // Should not warn.
    echo intdiv($x, 2);  // Should warn and infer type as string
}
