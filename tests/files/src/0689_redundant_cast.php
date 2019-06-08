<?php
/** @param object $o */
function check_impossible_cast(bool $b, int $i, float $f, string $s, array $a, $o, stdClass $stdClass) {
    $o2 = new $s();
    var_export([
        (bool)$b,
        (int)$i,
        (int)$f,
        (float)$i,
        (float)$f,
        (string)$s,
        (array)$a,
        (object)$o,
        (object)$stdClass,
        (object)$o2,
    ]);
}
