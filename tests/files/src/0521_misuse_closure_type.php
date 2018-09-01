<?php

call_user_func(function(string $arg) {
    // Phan detects invalid uses of Closures and classes.
    $i = rand(0,1);
    var_export(new $i());
    $o = new stdClass();
    var_export(new $o());
    $c = function(string $x) {};
    var_export(new $c());
    call_user_func([function () {}, 'name']);
    echo (function() {})->name();
    call_user_func([2, 'name']);
}, 'stdClass');
