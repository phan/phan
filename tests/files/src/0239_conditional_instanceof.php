<?php
class A {}
class B {}
function f(A $p) {}
$v = rand(0, 10) > 5 ? new A : new B;
if ($v instanceof B) {
    f($v);
}
