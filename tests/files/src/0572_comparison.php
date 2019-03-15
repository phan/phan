<?php

/**
 * @param int|false $x
 * @param ?int $y
 * @param 2|3 $z
 */
function test572($x, $y, $z) {
    if ($x > 3) {
        echo strlen($x);
    } else {
        echo strlen($x);
    }
    if ($y > 3) {
        echo strlen($y);
    } else {
        echo strlen($y);
    }
    if ($z >= 3) {
        echo strlen($z);
    } else {
        echo strlen($z);
    }
}

/**
 * @param int|false $x
 * @param ?int $y
 * @param 2|3 $z
 */
function test572b($x, $y, $z) {
    if ($x < 3) {
        echo strlen($x);
    } else {
        echo strlen($x);
    }
    if ($y < 3) {
        echo strlen($y);
    } else {
        echo strlen($y);
    }
    if ($z < 3) {
        echo strlen($z);
    } else {
        echo strlen($z);
    }
}
