<?php

/**
 * @param array $arg
 */
function x516($arg) {
    var_export($arg);
}

call_user_func(function() {
    while ($x = (rand(0, 2) > 0)) {
        x516($x);
    }
    // TODO: Make Phan aware that $x would be false after the loop

    while ($y = (rand(0, 10) > 0) ? 1 : 0) {
        x516($y);
    }
    // TODO: Make Phan aware that $x would be 0 after the loop
});

call_user_func(function() {
    while (!$x = (rand(0, 2) > 0)) {
        x516($x);
    }
    // TODO: Make Phan aware that $x would be true after the loop

    while (!$y = (rand(0, 10) > 0) ? 1 : 0) {
        x516($y);
    }
    // TODO: Make Phan aware that $x would be 1 after the loop
});
