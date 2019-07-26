<?php

function test_loop() {
    $found = false;
    while (!$found) {
        $found = rand(0,10) > 0;
        echo '.';
    }
}

function test_loop_infinite698() {
    $found = false;
    while (!$found) {
        $found = 0;
        echo '.';
    }
}
