<?php

/**
 * @param int[] $test
 */
function test284($test=[1]) {
    echo intdiv($test, 2);  // Should emit an error
}
test284(null);  // unrelated

/**
 * @param int[] $test
 */
function test284B($test = null) {
    echo intdiv($test, 2);
}
