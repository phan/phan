<?php
function test_switch167($x, $y) {
    switch ($x) {
        case true:
            return 1;
        case 1:
            return 2;
        case 1:  // duplicate
            return 3;
        case true:  // duplicate
            return 4;
        case []:
            return 5;
        case []:  // duplicate
            return 6;
        default:
            return 7;
        case null:
            return 8;
        case null:  // duplicate
            return 9;
        case $y:
        case $y:  // duplicate
            return 10;
    }
}
var_export(test_switch167([], 'other'));
