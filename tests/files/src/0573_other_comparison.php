<?php

/**
 * @param int[]|int $a
 */
function test573($a) {
    if ($a <= 10) {
        echo strlen($a);  // this rules out $a being an array.
    } else {
        echo strlen($a);
    }
}
