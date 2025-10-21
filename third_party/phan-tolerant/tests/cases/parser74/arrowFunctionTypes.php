<?php
$fn1 = fn(\stdClass $x) : stdClass => $x;
$fn2 = fn(): int => ($x);
$fn3 = fn($x = 42) => yield $x;
$fn4 = fn(&$x) => $x;
$fn5 = fn&(Ns\MyClass $x) : Ns\MyClass => $x;
$fn = fn($str) => preg_match($regex, $str, $matches) && ($matches[1] % 7 == 0);
$cb = fn() => fn() => $undef;
$fn = fn() => call_user_func(function () use ($arg) {
    var_export($arg);
    return $arg;
});
