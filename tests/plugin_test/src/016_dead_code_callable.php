<?php

function myfunc16() {
    echo __FUNCTION__ . "Called\n";
}

function myfunc16unused() {
    echo __FUNCTION__ . "Called\n";
}

function double16($x) { return $x * 2; }
function double16unused($x) { return $x * 2; }

function callable16() { return 16; }

class MyClass16 {
    public static function static_func_1() {
        echo __METHOD__ . "\n";
    }
    public static function static_func_unused() {
        echo __METHOD__ . "\n";
    }
    public function instanceFunc() {
        echo __METHOD__ . "\n";
    }
    public function instanceFuncUnused() {
        echo __METHOD__ . "\n";
    }
}

function user_defined_caller(callable $x) {
    call_user_func($x);
}

function main16() {
    user_defined_caller('myfunc16');
    user_defined_caller('MyClass16::static_func_1');
    $m = new MyClass16();
    user_defined_caller([$m, 'instanceFunc']);
    var_export(array_map('double16', [3]));
    return Closure::fromCallable('callable16');
}
main16();

var_export(user_defined_caller('undeclared_func_16'));
