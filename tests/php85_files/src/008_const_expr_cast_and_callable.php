<?php
// PHP 8.5 allows casts and first-class callables of functions and static methods in constant expressions.
namespace NS008;

#[\Attribute]
class Attr {
    public function __construct(public mixed $value) {}
}

class A {
    const F = strrev(...);
    const M = A::double(...);
    const S = (string) 1;
    const ARR = (array) 'a';
    public float $p = (float) '1.5';

    public static function double(int $x): int {
        return $x * 2;
    }

    public function withDefaults(\Closure $cb = strlen(...), object $o = (object) ['a' => 1]): int {
        return $cb('ab') + count((array) $o);
    }
}

const G = (bool) 1;

#[Attr((int) '3')]
function test(): void {
    $f = A::F;
    '@phan-debug-var $f';
    $m = A::M;
    '@phan-debug-var $m';
    $s = A::S;
    '@phan-debug-var $s';
    $arr = A::ARR;
    '@phan-debug-var $arr';
    echo $f('ab'), $m(2), $s, count($arr), (new A())->p, (new A())->withDefaults();
    var_export(G);
}
test();

// PHP rejects these.
class Invalid {
    const OBJECT_CAST = (object) [];        // only allowed where `new` is allowed
    const CALL = strlen('a');               // only first-class callables
    const STATIC_CLASS = static::class;
    const STATIC_CONST = static::OBJECT_CAST;
    const STATIC_METHOD = static::f(...);

    public static function f(int ...$args): int {
        return count($args);
    }

    public function params($a = new A(...), $b = new A(...[1])): void {
        var_export([$a, $b]);
    }
}
var_export([Invalid::OBJECT_CAST, Invalid::CALL, Invalid::STATIC_CLASS, Invalid::STATIC_CONST, Invalid::STATIC_METHOD]);
(new Invalid())->params();
