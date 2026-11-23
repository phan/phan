<?php
// PHP 8.3+ allows any expression as the initializer of a static variable.
namespace NS009;

function compute(): int {
    return 1;
}

function f(int $x): string {
    static $a = compute();
    static $b = $x + 1;
    static $c = new \ArrayObject([$x]);
    static $d = $undefined;
    static $e = compute(1);
    // Casts and first-class callables are allowed here since PHP 8.3, while constant expressions only allow them since PHP 8.5.
    static $s = (string) 1;
    static $cb = strlen(...);
    return "$a $b $d $e $s " . count($c) . $cb('x');
}
echo f(1);

function g(int $y): int {
    // The initializer only runs on the first call: later calls use the $y passed in.
    static $z = ($y = 2);
    return $y + $z;
}
echo g(1);

function h(string $value, bool $cond): string {
    if ($cond) {
        $x = 1;
    }
    // null if $x is undefined
    static $s = $x;
    '@phan-debug-var $s';
    static $cached = ($value = 42);
    '@phan-debug-var $value';
    return "$value $s $cached";
}
echo h('a', true);

function k(): array {
    // $y is defined when it is read: both are evaluated in the same run of the initializer.
    static $pair = [$y = 1, $y];
    '@phan-debug-var $pair';
    return $pair;
}
var_export(k());
