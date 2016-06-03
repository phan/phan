<?php

/**
 * @param int[][] $p
 */
function f($p) {
    foreach ($p as $a) {
        $x = array_slice($a, 0, 1);
    }
}

f([ [1, 2], [3, 4] ]);
