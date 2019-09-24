<?php
function test_infinite($x) {
    test_infinite($x+1);
}
function test_infinite2($x) : string {
    if ($x > 0) {
        return $x;
    }
    return test_infinite2($x);
}
function test_infinite3() : int {
    global $argc;
    if ($argc > 3) {
        return $argc;
    }
    // should warn, not able to check global state.
    return test_infinite3();
}
// Should not warn
function test_infinite4(int $x) : int {
    $x += 3;
    if ($x >= 10) {
        return $x;
    }
    return test_infinite4($x);
}
// Should warn (modification isn't done in this branch)
function test_infinite5(int $x) : int {
    if ($x >= 10) {
        return test_infinite5($x);
    }
    $x += 3;
    return $x;
}
