<?php

function foo382() : ?string {
    return 'x';
}

/** @return string|false */
function bar382() {
    if (rand() % 2 > 0) {
        return 'x';
    } else {
        return false;
    }
}

/** @param int $x */
function expect_int382($x) {
}

function test() {
    echo expect_int382(foo382() ?: '');
    echo expect_int382(bar382() ?: ['x']);
}
