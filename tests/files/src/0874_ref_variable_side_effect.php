<?php
function test874($x) {
    $total = 0;
    $cb = function (array $values) use (&$total) {
        // Should not emit PhanSideEffectFreeForeachBody because the reference $total is modified
        foreach ($values as $v) {
            $total += $v;
        }
    };
    $cb($x);
    return $total;
}
