<?php

call_user_func(function () {
    $a = true;  // PhanUnusedVariable should be emitted
    if (rand(0, 1) > 0) {
        $a = true;
        var_export($a);
    }
});

call_user_func(function () {
    $a = true;  // PhanUnusedVariable should not be emitted
    if (rand(0, 1) > 0) {
        if (rand(0, 1) > 0) {
            $a = true;
        }
        var_export($a);
    }
});

call_user_func(function () {
    $a = true;  // PhanUnusedVariable should be emitted
    if (rand(0, 1) > 0) {
        if (rand(0, 1) > 0) {
            $a = true;
        } else {
            $a = 'x';
        }
        var_export($a);
    }
});
