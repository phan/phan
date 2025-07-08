<?php

// Implementation intentionally omitted, this test is just about accessing the property

/** @template T */
class X {
    /** @var T */
    public $u;
}
function getX(): X {
    return new X;
}

$x1 = getX();
$y1 = $x1->u; // whatever the type of this is, it must not be "T"
'@phan-debug-var $x1, $y1';

$x2 = new X;
$y2 = $x2->u;
'@phan-debug-var $x2, $y2';
