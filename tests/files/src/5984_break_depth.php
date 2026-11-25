<?php

namespace NS5984;

// `break 2` leaves both loops: the function can reach its end
function break2(): int {
    while (true) {
        while (true) {
            break 2;
        }
    }
}

// The inner loop is only left by returning
function innerReturns(): int {
    while (true) {
        while (true) {
            return 1;
        }
    }
}

// `continue 2` stays in the outer loop
function continue2(int $x): int {
    while (true) {
        switch ($x) {
            case 1:
                $x++;
                continue 2;
            default:
                return $x;
        }
    }
}

// The type after the loops includes what was assigned before `break 2`
function typeAfterBreak2(array $a): ?string {
    $x = null;
    foreach ($a as $v) {
        foreach ($v as $w) {
            if ($w) {
                $x = 'found';
                break 2;
            }
        }
    }
    '@phan-debug-var $x';
    return $x;
}
