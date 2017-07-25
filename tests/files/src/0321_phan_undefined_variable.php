<?php
function fooCondUndef321() {
    $x = $cond ? sprintf('a%d', 2) : null; // should warn for $cond
    $array = [];
    $array[$undef] = 2;  // also should warn for $undef
    $y = $array[$undefB];  // also should warn for $undefB
}
$x321 = $cond321 ? sprintf('a%d', 2) : null; // should warn for $cond in global scope
$array321 = [];
$array321[$undef321] = 2;  // also should warn for $undef
$y321 = $array321[$undefB321];  // also should warn for $undefB
