<?php

namespace NS938;

use stdClass;

class X {
    /** @var stdClass */
    public $xProp;
}
/** @property int $iProp */
interface I {
}

function test(X $x) {
    $x->iProp = 42;
    if ($x instanceof I) {
        echo strlen($x->xProp);  // should warn about type
        echo strlen($x->iProp);  // should warn about type
        $x->xProp = new \stdClass();
        $x->iProp = 42;
    }
}
test(new X());

function test_exclude(X $x, stdClass $y): X {
    if (!$x instanceof I) {
        return;
    }
    $z = rand(0, 1) ? $x : $y;
    '@phan-debug-var $z';
    if ($z instanceof I) {
        '@phan-debug-var $z';  // stdClass&I is technically also possible but this test doesn't use strict return type checking
        return $z;
    }
    return $z;  // wrong, this is stdClass
}
