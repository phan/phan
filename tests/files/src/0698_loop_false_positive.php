<?php

function test_loop() {
    $found = false;
    while (!$found) {
        $found = rand(0,10) > 0;
        echo '.';
    }
}
