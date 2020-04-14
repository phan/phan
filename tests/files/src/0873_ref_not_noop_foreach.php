<?php
// Should not emit PhanSideEffectFreeForeachBody since the loop variable is modified by reference
function test(array $arr) {
    foreach ($arr as &$val) {
        $val = (int)$val;
    }
    return $arr;
}
