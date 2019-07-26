<?php
call_user_func(function () {
    $x = null;
    if (rand() % 2) {
        $x++;
    } else {
        echo strlen($x);
    }
});
