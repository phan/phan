<?php

/**
 * Test for GitHub issue #5455
 * When multiple ternary expressions access different keys of the same
 * array<string,mixed> parameter inside a function call (or sequential
 * statements), the type narrowing from the first ternary should not
 * cause false PhanTypePossiblyInvalidDimOffset warnings on subsequent
 * ternary accesses.
 * @phan-file-suppress PhanUnreferencedFunction,PhanParamTooMany
 */

class TestClass5912 {}

/**
 * Case 1: Original bug report - two ternaries in constructor call
 * @param array<string,mixed> $arr
 */
function test5912_constructor_call(array $arr) {
    return new TestClass5912(
        $arr['x'] ? [$arr['x']] : null,
        $arr['y'] ? [$arr['y']] : null
    );
}

/**
 * Case 2: Three ternaries in constructor call
 * @param array<string,mixed> $arr
 */
function test5912_triple_ternary(array $arr) {
    return new TestClass5912(
        $arr['x'] ? [$arr['x']] : null,
        $arr['y'] ? [$arr['y']] : null,
        $arr['z'] ? [$arr['z']] : null
    );
}

/**
 * Case 3: Sequential assignments with ternaries
 * @param array<string,mixed> $arr
 */
function test5912_sequential_assignments(array $arr) {
    $a = $arr['x'] ? [$arr['x']] : null;
    $b = $arr['y'] ? [$arr['y']] : null;
    var_dump($a, $b);
}

/**
 * Case 4: Regular function call with ternaries
 * @param array<string,mixed> $arr
 */
function test5912_function_call(array $arr) {
    return array_merge(
        $arr['x'] ? [$arr['x']] : [],
        $arr['y'] ? [$arr['y']] : []
    );
}
