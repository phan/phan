<?php
class Test {
    static function fn() { echo "Hello"; }
}
$a = ['Test','fn'];
var_export(is_callable($a));
$a();

function f(Closure $c) {}
f($a);
