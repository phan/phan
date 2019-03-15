<?php

class A521 {
    public function __construct(string $arg) {
    }
}

call_user_func(function(string $arg) {
    // Phan detects invalid uses of Closures and classes.
    $i = rand(0,1);
    var_export(new $i());
    $o = new stdClass();
    var_export(new $o());  // NOTE: This is valid. See https://github.com/phan/phan/issues/1926
    $c = function(string $x) {};
    var_export(new $c());
    call_user_func([function () {}, 'name']);
    echo (function() {})->name();
    call_user_func([2, 'name']);
    $a = new A521('x');
    var_export(new $a());
}, 'stdClass');
