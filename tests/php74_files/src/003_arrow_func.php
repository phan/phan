<?php
// Tests functionality from https://wiki.php.net/rfc/arrow_functions_v2
function test_arrow_func() {
    $x = 123;
    $fn1 = fn(array $x) => $x;
    $fn2 = fn(): int => $x;
    $fn3 = fn($x = 42) => yield $x;
    $fn4 = fn(&$x) => $x;
    $fn5 = fn&($x) => $x;
    $fn6 = fn($x, ...$rest) => $rest;
    $fn7 = static fn() => 1;

    $regex = '/(fo*)/';
    $fn = fn($str) => preg_match($regex, $str, $matches) && ($matches[1] % 7 == 0);

    var_export($fn1([]));
    echo strlen($fn1());

    echo strlen($fn2('extra'));  // Too many parameters, actually int
    echo strlen($fn3());  // should infer generator
    $arg = 'arg';
    echo strlen($fn4($arg));  // works
    echo strlen($fn4('not a reference'));  // emits PhanTypeNonVarPassByRef
    $fn5($arg) = 2;  // should not warn, doesn't properly infer that $arg is set

    echo strlen($fn6('arg'));  // infers array<int,mixed> and warns
    echo intdiv($fn7(), 2);
    echo intdiv($fn('foooooooooooooooo'), 2);

    fn() => 2;  // PhanNoopClosure

    $cb = fn() => fn() => $undef;  // PhanUndeclaredVariable
    var_export($cb()());

    $arg = [2];
    $argUnused = [3];  // phan will warn that this is unused (it does not begin with raii, unused, or _)
    $fn = fn() => call_user_func(function () use ($arg) {
        var_export($arg);
        return $arg;
    });
    echo strlen($fn());  // PhanTypeMismatchArgumentInternal
}
