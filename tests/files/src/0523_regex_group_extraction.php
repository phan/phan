<?php
call_user_func(function (string $str) {
    if (preg_match('/^(a)/', $str, $matches) > 0) {
        echo $matches[2];  // Wrong, there is only 1 group
    }
    if (preg_match('/^(a)/', $str, $matches, PREG_OFFSET_CAPTURE) > 0) {
        var_export($matches[2]);  // Wrong, there is only 1 group
    }
    if (preg_match('/(?P<name>a)/', $str, $named_matches, PREG_OFFSET_CAPTURE) > 0) {
        echo strlen($named_matches);  // Wrong, it's an array.
    }
    if (preg_match('/(?P<name>a)/', $str, $named_matches) > 0) {
        echo strlen($named_matches);  // Wrong, it's an array.
    }
}, 'abc');
