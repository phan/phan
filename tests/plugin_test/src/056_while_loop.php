<?php
call_user_func(function (int $a) {
    $b = rand(0, $a);
    while ($a != $b) {
        $b = rand(0, $a + 1);  // should not emit PhanUnusedVariable
        $c = $a;
    }
}, 2);
