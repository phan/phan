<?php

function f762() {
    $x = null;
    call_user_func_array('f762clos1', [&$x]);
    if ($x===0.5) {
        return 'foo';
    }
	if ($x instanceof \stdClass) {
        return false;
    }

    $y = false;
    call_user_func_array('f762clos2', [ $y ]);
    if ($y===2) {
        return 'foo';
    }

    $z = false;
    call_user_func_array('f762clos3', [ &$z ]);
    if ($z === 2) {
        return 'foo';
    }
}

function f762clos1(&$arg) {
    $arg = rand();
}

function f762clos2($arg) { // No reference
    $arg = 2;
}

function f762clos3(&$arg) {
    if (rand()) {
        $arg = 2;
    }
}