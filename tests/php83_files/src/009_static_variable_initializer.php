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
    return "$a $b $d $e " . count($c);
}
echo f(1);
