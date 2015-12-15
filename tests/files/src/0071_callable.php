<?php
class Test {
    static function fn() { echo "Hello"; }
}
$a = ['Test','fn'];
echo is_callable($a);
$a();

function f(Closure $c) {}
f($a);
