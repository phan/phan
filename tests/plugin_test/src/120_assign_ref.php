<?php
function test120(int $a) {
    $b = &$a;
    $b++;  // Should not emit PhanUnusedVariable
    return $a;
}

function test120b(int $a) {
    $b = &$a;
    $a++;  // Should not emit PhanUnusedVariable
    return $b;
}
var_export([test120(2), test120b(3)]);
