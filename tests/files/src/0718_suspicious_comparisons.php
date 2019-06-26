<?php

final class FinalClass718 {}

function suspicious_comparisons(iterable $i, array $a, stdClass $s, float $f, bool $b) {
    $f = new FinalClass718();

    var_export($i <= $a);  // not necessarily suspicious
    var_export($i <= $f);
    var_export($a <= $f);
    var_export($f <= null);
    var_export($f > false);
    var_export($a == 2.3);
    var_export($a != $f);
    var_export($i != $f);
    var_export($f != false);
    var_export($f < true);
    var_export($f == $b);
}
