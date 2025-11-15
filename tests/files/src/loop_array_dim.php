<?php

function test_loop_assignment_is_seen() {
    $arr = [];
    for ($i = 0; $i < 3; $i++) {
        $k = $GLOBALS['x'];
        if (rand()) {
            $arr[$k] = 42;
        } else {
            echo $arr[$k];  // should not warn - previous iterations may have added $k
        }
    }
}

function test_non_loop_still_warns() {
    $arr = [];
    $k = 'key';
    if (rand()) {
        $arr[$k] = 1;
    } else {
        echo $arr[$k];  // should continue to warn
    }
}
