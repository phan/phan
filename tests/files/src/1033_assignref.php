<?php
call_user_func(function() {
    $a = [2];
    $b = ['c' => new stdClass()];

    [&$x] = $a;
    list('c' => &$y) = $b;
    echo strlen($x);
    echo strlen($y);
});
