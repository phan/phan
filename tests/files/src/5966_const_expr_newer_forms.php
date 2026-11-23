<?php
// Targeting PHP 8.1: forms PHP added to constant expressions in later versions.
namespace NS5966;

class A {
    const F = strlen(...);          // PHP 8.5+
    const M = A::f(...);            // PHP 8.5+
    const S = (string) 1;           // PHP 8.5+
    public $p = (int) '5';          // PHP 8.5+

    public static function f(): int {
        static $a = A::compute();   // PHP 8.3+
        return $a;
    }

    public static function compute(): int {
        return 1;
    }
}
const G = (bool) 1;                 // PHP 8.5+
var_export([A::F, A::M, A::S, (new A())->p, A::f(), G]);

function defaults(\Closure $cb = strlen(...), string $s = (string) 1): int {  // PHP 8.5+
    return $cb($s);
}
$closure = function (string $s = (string) 2): string {  // PHP 8.5+
    return $s;
};
// The class name of `new` and the name of a first-class callable can be operations on literals, which PHP evaluates at compile time.
function folded(object $o = new ('std' . 'Class')): object {
    return $o;
}
const FOLDED = ('strlen')(...);  // PHP 8.5+
var_export([defaults(), $closure(), folded(), FOLDED]);
