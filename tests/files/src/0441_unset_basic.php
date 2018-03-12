<?php
call_user_func(function() {
    global $argv;

    $myVar = 'someVal';
    unset($myVar);
    echo $myVar;  // should warn

    $x = ['a' => 2, 'b' => $argv];
    unset($x['a']);
    echo strlen($x);
});
