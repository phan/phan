<?php

namespace NSwitch;

function test1(int $x) {
    switch ($x) {
    case 2:
        $y = 20; // should warn
    default:
        $y = 30;
    }
    return $y;
}
function test2(int $x) {
    switch ($x) {
    default:
        $y = 30;  // should warn
    case 2:
        $y = 20;
    }
    return $y;
}
