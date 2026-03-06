<?php

function takesFloat(float $x): void {
    echo $x;
}

function takesString(string $x): void {
    echo $x;
}

(function (int $a, int $b, int $c) {
    // int to float is fine
    takesFloat($a);
    // positive-int to float is fine
    if ($b > 0) {
        takesFloat($b);
    }
    // negative-int to float is fine
    if ($c < 0) {
        takesFloat($c);
    }
    // positive-int to string should still warn
    if ($b > 0) {
        takesString($b);
    }
    // negative-int to string should still warn
    if ($c < 0) {
        takesString($c);
    }
})(0, 1, -1);
