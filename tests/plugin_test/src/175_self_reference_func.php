<?php
function fib(int $n) {
    return $n <= 1 ? $n : fib($n - 1) + fib($n - 2);
}
class MyFib {
    public function fib(int $n) {
        return $n <= 1 ? $n : self::fib($n - 1) + self::fib($n - 2);
    }
}
var_export(new MyFib());
