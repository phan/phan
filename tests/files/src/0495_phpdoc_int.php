<?php

/**
 * @param 1 $x
 * @return 4
 */
function testSingleInt(int $x) {
    if ($x) {
        return 4;
    }
    return 5;
}
testSingle(1);
testSingle(2);

/**
 * @param 1|2 $x
 * @return 4|5
 */
function testDoubleInt(int $x) {
    if ($x > 4) {
        return 4;
    }
    if ($x) {
        return 6;
    }
    return 5;
}
testDoubleInt(1);
testDoubleInt(2);
testDoubleInt(-1);
testDoubleInt(rand(0,9));

/**
 * @param ?-1 $x (either -1 or null)
 * @return ?4
 */
function testNullableInt($x) {
    if (!$x) {
        return 4;
    }
    if ($x > 0) {
        return 2;
    }
    return null;
}
testNullableInt(-1);
testNullableInt(1);  // this warns
testNullableInt(null);
