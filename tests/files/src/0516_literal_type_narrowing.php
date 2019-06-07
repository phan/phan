<?php
call_user_func(function() {
    $x = 2;
    assert(is_int($x));  // Emits PhanRedundantCondition
    assert(is_numeric($x));  // Emits PhanRedundantCondition
    echo strlen($x);

    $y = 'a string';
    assert(is_string($y));
    echo intdiv($y, 2);
});
