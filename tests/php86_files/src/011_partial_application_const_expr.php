<?php
// PHP 8.6 allows partial application of functions and static methods in constant expressions.
namespace NS011;

function join2(string $a, string $b): string {
    return $a . $b;
}

class A {
    const P = join2(?, 'x');
    const Q = A::add(1, ?);

    public static function add(int $a, int $b): int {
        return $a + $b;
    }
}

function test(): void {
    $p = A::P;
    '@phan-debug-var $p';
    $q = A::Q;
    '@phan-debug-var $q';
    echo $p('y'), $q(2);
}
test();
