<?php

namespace NS333;

function break2(): int {
    while (true) {
        while (true) { break 2; }
        return 1;
    }
}
function break1(): int {
    while (true) {
        while (true) { break; }
        return 1;
    }
}
function continue2(array $a): int {
    foreach ($a as $x) {
        foreach ($a as $y) {
            if ($y) { continue 2; }
            return 1;
        }
        return 2;
    }
    return 3;
}
function switchBreak2(int $x): int {
    while (true) {
        switch ($x) {
            case 1: break 2;
            default: return 1;
        }
    }
}
function switchContinue2(int $x): int {
    while (true) {
        switch ($x) {
            case 1: $x++; continue 2;
            default: return 1;
        }
    }
}
function break3(): int {
    while (true) {
        while (true) {
            while (true) { break 3; }
        }
    }
}
function unreachableAfterInner(): int {
    while (true) {
        while (true) { break 2; }
        echo "unreachable";
    }
    return 1;
}
function goto_(): int {
    while (true) {
        goto done;
        done:
        while (true) { break 2; }
        return 1;
    }
}
function continue3(array $a): int {
    foreach ($a as $x) {
        foreach ($a as $y) {
            foreach ($a as $z) {
                if ($z) {
                    continue 3;
                }
            }
        }
    }
    return $x ?? 0;
}
function break3ThenReturn(): int {
    while (true) {
        while (true) {
            while (true) {
                break 3;
            }
        }
    }
    return 1;
}
