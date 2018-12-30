<?php

function test_break_levels(string $x) {
    foreach ([2,3] as $i) {
        if ($i > 3) {
            continue 2;
        } elseif ($i > 0) {
            switch ($x) {
            case 'x':
                break 2;
            case 'y':
                break 3;
            case 'z':
                continue;
            default:
                continue 4;
            }
        }
        break;
    }
}
function test_break_no_loop(bool $cond) {
    if ($cond) {
        break;
    }
    continue;
}
if (rand() % 2 > 0) {
    break;
} elseif (rand() % 2 > 0) {
    continue 3;
} elseif (rand() % 2 > 0) {
    break 1;
}

function test_break_depth_to_continue(string $x) {
    switch ($x) {
        case 'loop':
            while (rand() % 2 > 0) {
                continue 2;  // should warn about this. TODO: The error message isn't ideal.
            }
            break;
    }
}
