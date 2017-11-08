<?php

const Z = 1;
const Y = 1;
const A = true;
const B = false;

class Test {
    const XYZ = 1;
    const TRUE = true;
    const FALSE = false;
}

$a = [
    Y => 1,
    Z => 2,
    Test::XYZ => 3,
    Test::TRUE => true,
    Test::FALSE => false,
    A => true,
    B => false,
    false => 11,
    true => 13,
    null => 14,
    '' => 15,
    // Could analyze these later, but not doing that yet. For now, just check that this doesn't warn.
    __FILE__ => 15,
    __LINE__ => 15,
];
