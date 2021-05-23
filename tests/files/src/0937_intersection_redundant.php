<?php

namespace NS937;

interface I1 {}
interface I2 {}
interface I3 extends I1 {}

function test1(I3 $value) {
    if ($value instanceof I2) {
        return $value instanceof I2; // redundant
    }
    return true;
}
function test2(I3 $value) {
    if ($value instanceof I2) {
        return $value instanceof I1; // redundant
    }
    return false;
}
