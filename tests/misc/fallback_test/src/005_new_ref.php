<?php

class A5 {
    /** @var int */
    public $prop = 2;
}
function test5() : string {
    $x = &new A5();
    return $x->prop;
}
