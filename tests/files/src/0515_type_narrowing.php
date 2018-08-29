<?php

call_user_func(function() {
    if (rand() % 2 > 0) {
        $x = null;
    } else {
        $x = count($_SERVER);
    }
    if (is_numeric($x)) {
        echo strlen($x);  // wrong
    }

    if (rand() % 2 > 0) {
        $y = null;
    } else {
        $y = 2.3;
    }
    if (is_numeric($y)) {
        echo strlen($y);  // wrong
    }

    if (rand() % 2 > 0) {
        $z = null;
    } else {
        $z = (string)count($_SERVER);
    }
    if (is_numeric($z)) {
        echo count($z);  // wrong
    }
});
