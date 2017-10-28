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
];
