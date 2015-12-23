<?php
$a = 1;
$b = 'str';
$d = 'str';
$e = 1;
if (true) {
    $a = 'str';
    $b = 1;
    $c = 2;
    $d = 3;
} else {
    $a = 'str';
    $c = 3;
    $d = 3;
}
function f(int $v, int $w, int $x, int $y, int $z) {}
f($a, $b, $c, $d, $e);
