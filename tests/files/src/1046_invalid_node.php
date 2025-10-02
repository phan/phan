<?php

// These are syntax errors
call_user_func(function () {
    global $argv;
    [
        $a,
        ...$b
    ] = [1, 2, 3];
    list(
        $prog,
        $command,
        ...$b
    ) = $argv;
});
