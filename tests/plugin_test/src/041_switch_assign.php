<?php

function test_switch_unused(int $v) {
    $result = 'none';
    switch($v) {
        case 1:
            $result = 'x';  // should warn
            return;
        case 2:
            if (rand() % 2 > 0) {
                $result = 'x';  // should not warn
                $myUnused = 'x';
                break;
            }
            $result = 'default';
            $myUnused = 'default';
            break;
    }
    echo $result;
}

function test_switch_in_loop(int $v) {
    $result = 'none';
    for ($i = 1; $i < 10; $i++) {
        switch($v) {
            case 2:
                if (rand() % 2 > 0) {
                    echo $result;
                    $myUnused = 'x';
                    break;
                }
                $result = 'default' . $i;  // should not warn
                $myUnused = 'default';
                break;
        }
    }
}
test_switch_unused(2);
test_switch_in_loop(2);
