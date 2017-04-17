<?php

/**
 * Test that
 * @var \ is $v - not a good var annotation
 * @var \ $x is not good either
 * @var string|\NS\ $y is not good either
 */
function testannotation283($y) {
    $x = $y;
    return $x;
}
