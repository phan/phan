<?php

class C346 {}

function f346(C346 $p) {}
function test346() {
    $v1 = 'C346';
    $v2 = new C346;
    if ($v2 instanceof $v1) {
        f346($v2);
        intdiv($v2, 3);  // should warn.
    }
}

function test346B($v1) {
    $v2 = new C346;
    if ($v1 instanceof $v2) {
        f346($v1);
        echo intdiv($v1, 2);  // should warn.
    }
}
function test346C($v1) {
    $v2 = 2;
    if ($v1 instanceof $v2) {  // should warn but should never set the type to int.
        f346($v1);
    }
}
