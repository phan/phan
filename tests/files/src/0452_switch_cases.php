<?php

call_user_func(function(string $x) {
    $val = [];
    switch($x) {
    case 'a':
        throw new RuntimeException("foo");
    default:
        $val = false;
    }
    echo count($val);

    $v2 = [];
    switch($x) {
    case 'b':
        $v2 = new stdClass();
        break;
    case 'c':
        $v2 = 2.3;
        break;
    default:
        throw new RuntimeException("foo");
    }
    echo count($v2);
}, $argv[1]);
