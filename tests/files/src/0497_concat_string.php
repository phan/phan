<?php

/**
 * @return 'xyz'
 */
function test() {
    $x = 'xy';
    if (rand(0,5) % 2 > 0) {
        return $x . 'z';
    }
    if (rand(0,5) % 2 > 0) {
        return "'$x${x}'";
    }
    return $x . ' ';
}
