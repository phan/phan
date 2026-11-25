<?php

namespace NS331;

// AlwaysReturnPlugin: a goto stays in the function. What matters is whether the code after a label can reach the end.
function returnsAfterLabel(int $x): int {
    if ($x > 1) { goto done; }
    $x++;
    done:
    return $x;
}
function loopsWithGoto(int $x): int {
    retry:
    if ($x > 10) { return $x; }
    $x++;
    goto retry;
}
function fallsOffAfterLabel(int $x): int {
    if ($x > 1) { goto done; }
    return $x;
    done:
    echo "no return";
}
function fallsOffAfterNestedLabel(int $x): int {
    if ($x > 1) { goto done; }
    if ($x) {
        return 1;
        done:
        echo "x";
    } else {
        return 2;
    }
}
function returnsAfterNestedLabel(int $x): int {
    if ($x > 1) { goto done; }
    if ($x) {
        return 1;
        done:
        echo "x";
    }
    return 2;
}
function fallsOffAfterLabelInLoop(int $x): int {
    foreach ([1, 2] as $v) {
        if ($v > $x) { goto next; }
        return $v;
        next:
    }
}
function gotoInClosure(int $x): int {
    $f = function () { goto a; a: echo 1; };
    $f();
    return $x;
}
function noGoto(int $x): int {
    if ($x) { return 1; }
}
