<?php
// https://github.com/phan/phan/issues/105
class A {
    const C1 = B::C2;
    public function f($listing_format = self::C1) {
        echo count($listing_format);
    }
}
class B {
    const C2 = 'c2';
}
(new A())->f();

function foo() {
    return function ($x = CONSTVAL) { return $x; };
}
define('CONSTVAL', 'value');
$x = [CONSTVAL];

function fooMissing() {
    return function ($x = CONSTVAL_MISSING) { return $x; };
}
$x = [CONSTVAL_MISSING];
