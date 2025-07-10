<?php
interface I {}

// Implementation intentionally omitted, this test is just about accessing the property
/** @template T */
class X {
    /** @var T */
    public $u;
}
$x0 = new X;
$y0 = $x0->u;
'@phan-debug-var $x0, $y0';


function getX1(): X {
    return new X;
}
$x1 = getX1();
$y1 = $x1->u; $x1->u[] = 123;
'@phan-debug-var $x1, $y1';


/**
 * This is a mistake, but it still shouldn't leak the template type
 * @template U
 * @return X<U>
 */
function getX2(): X {
    return new X;
}
$x2 = getX2();
$y2 = $x2->u;
'@phan-debug-var $x2, $y2';


/**
 * This more often arises as a result of `if ($x instanceof I) {...}`
 * @return X&I
 */
function getX3() {
    return new X;
}
$x3 = getX3();
$y3 = $x3->u;
'@phan-debug-var $x3, $y3';


/**
 * Phan doesn't support extracting types out of an intersection type parameter, but it still shouldn't leak the template type
 * @template U
 * @param I&X<U> $x
 * @return X<U>
 */
function getX4(): X {
    return new X;
}
$x4 = getX4();
$y4 = $x4->u;
'@phan-debug-var $x4, $y4';
