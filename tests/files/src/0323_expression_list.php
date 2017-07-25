<?php

/**
 * @param int $x
 * @param int $y
 */
function testLoopCond323($x, $y) {
    // Only the last expression in a for loop's expression list (for condition) matters.
    for ( ; is_string($x), 42; ) {
        echo strlen($x);
        break;
    }
    for ( ; 42, is_string($y); ) {
        echo strlen($y);
        break;
    }
}
