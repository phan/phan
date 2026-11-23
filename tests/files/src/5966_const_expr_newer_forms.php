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
