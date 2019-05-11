<?php
function test_undef_var_in_closure() {
    $cb = function () use ($var) {
        echo intdiv($var, 2);
        var_dump($var);
    };
    $cb();
}
test_undef_var_in_closure();
