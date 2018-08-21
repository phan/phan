<?php
call_user_func(function() {
    $x = 2;
    assert(is_int($x));
    assert(is_numeric($x));
    echo strlen($x);

    $y = 'a string';
    assert(is_string($y));
    echo intdiv($y, 2);
});
