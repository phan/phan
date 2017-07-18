<?php

class A347 {
    public function myMethod() {}
}

/**
 * @return A347|null
 */
function A347F() {
}

// Regression test for ternary operator in global scope.
$c = A347F();
$d = !is_null($c) ?
    intdiv($c, 2) :
    intdiv(4, $c);
$e = A347F();
if (!is_null($e)) {
    intdiv($e, 2);
} else {
    intdiv($e, 2);
}
$f = A347F();
// Regression test for ternary operator as statement in global scope.
!is_null($f) ?
    intdiv($c, 2) :
    intdiv(4, $c);
