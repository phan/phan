<?php
call_user_func(function () {
    $catch = 22;

    do {
        if (rand(0, 1) > 0) {
            $catch = 0;
            break;
        }
    } while (false);

    print $catch;
});

call_user_func(function () {
    $catch = 22;

    while (rand(0, 1) > 0) {
        if (rand(0, 1) > 0) {
            $catch = 0;
            break;
        }
    }

    print $catch;
});

call_user_func(function () {
    $a = 22;

    do {
        if (rand(0, 1) > 0) {
            $a = 0;
            break;
        }
    } while ($a);
});

call_user_func(function () {
    $a = 22;  // should warn about being unused

    do {
        if (rand(0, 1) > 0) {
            $a = 0;
            break;
        } else {
            $a = 2;
        }
    } while ($a);
});
