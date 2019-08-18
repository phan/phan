<?php

function g148($a, $b) {
    return sqrt(f148($a, $b));
}

function square148($a) {
    return $a * $a;
}

function f148($a, $b) {
    return square148($a) + square148($b);
}
f148(2, 3);
g148(2, 3);
square148(4);
