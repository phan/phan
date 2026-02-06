<?php

class TestObj5906 {}

function test_object_vs_scalar(TestObj5906 $obj, int $i, string $s, float $f) {
    // Should warn - object ordering with non-object
    var_export($obj < $i);
    var_export($obj > $s);
    var_export($obj <= $f);
    var_export($i >= $obj);
    var_export($obj <=> $i);
}

function test_object_vs_object(TestObj5906 $a, TestObj5906 $b) {
    // Should NOT warn - object-to-object ordering is valid
    var_export($a < $b);
    var_export($a <=> $b);
}

function test_equality(TestObj5906 $obj, int $i) {
    // Should NOT warn - equality operators are fine
    var_export($obj == $i);
    var_export($obj === $i);
    var_export($obj != $i);
}

/**
 * @param mixed $m
 */
function test_mixed(TestObj5906 $obj, $m) {
    // Should NOT warn - mixed could be anything
    var_export($obj < $m);
}

/**
 * @param TestObj5906|int $u
 */
function test_union($u, int $i) {
    // Should NOT warn - union includes non-object type
    var_export($u < $i);
}
