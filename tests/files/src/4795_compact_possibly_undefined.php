<?php

/**
 * Test case for issue #4795
 * compact() should detect possibly undefined variables
 * @param bool $fail
 * @return array<string,bool>
 */
function test_compact_possibly_undefined($fail = false) {
    if (!$fail) {
        $success = true;
    }
    return compact('success');
}

/**
 * Test definitely undefined variable
 */
function test_compact_definitely_undefined() {
    return compact('undefined_var');
}

/**
 * Test always defined variable - no warning expected
 */
function test_compact_always_defined() {
    $defined = 'value';
    return compact('defined');
}

/**
 * Test multiple variables with mixed states
 * @param bool $cond
 */
function test_compact_multiple_vars($cond) {
    $a = 1;
    if ($cond) {
        $b = 2;
    }
    $c = 3;
    return compact('a', 'b', 'c');
}

/**
 * Test with nested conditions
 * @param bool $x
 * @param bool $y
 */
function test_compact_nested_conditions($x, $y) {
    if ($x) {
        if ($y) {
            $nested = 'value';
        }
    }
    return compact('nested');
}

/**
 * Test with variable that's unset
 * @param bool $cond
 */
function test_compact_unset_variable($cond) {
    $temp = 'value';
    if ($cond) {
        unset($temp);
    }
    return compact('temp');
}
