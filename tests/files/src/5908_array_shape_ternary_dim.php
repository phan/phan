<?php

/**
 * Test for GitHub issue #5444
 * When mixed-typed variables have array shape fields added through sequential
 * ternary/conditional expressions, the fields should be merged into a single
 * array shape rather than creating separate shapes that cause false
 * PhanTypePossiblyInvalidDimOffset warnings on already-established fields.
 * @suppress PhanUnusedVariable
 */

/**
 * Case 1: Exact reproduction from the bug report
 * Sequential ternary dim access on $GLOBALS (mixed type)
 * The fix ensures 'a' is not falsely marked as possibly-undefined
 * after the second ternary accesses 'b'.
 * @suppress PhanPluginDuplicateConditionalTernaryDuplication
 */
function test5908_globals_ternary() {
    $arr = $GLOBALS['x'];

    $a = $arr['a'] ? $arr['a'] : [];
    $b = $arr['b'] ? [] : 'x';
    var_dump($a, $b);
}

/**
 * Case 2: Accessing an already-established field after a second conditional
 * This is the key regression test: without the fix, accessing $arr['a']
 * after the second ternary would falsely warn about possibly-invalid offset.
 * @suppress PhanPluginDuplicateConditionalTernaryDuplication
 */
function test5908_no_false_positive_on_established_field() {
    $arr = $GLOBALS['x'];

    $a = $arr['a'] ? $arr['a'] : [];
    $b = $arr['b'] ? [] : 'x';
    // After both ternaries, 'a' should still be definitively in the shape
    // and this access should NOT produce PhanTypePossiblyInvalidDimOffset
    echo $arr['a'];
    var_dump($a, $b);
}

/**
 * Case 3: If-else equivalent of the ternary pattern
 */
function test5908_if_else() {
    $arr = $GLOBALS['x'];

    if ($arr['a']) {
        $a = $arr['a'];
    } else {
        $a = [];
    }

    if ($arr['b']) {
        $b = [];
    } else {
        $b = 'x';
    }
    // 'a' should still be valid after the second if-else
    echo $arr['a'];
    var_dump($a, $b);
}

/**
 * Case 4: Three consecutive dim accesses
 * Earlier fields should remain valid after later conditionals
 */
function test5908_three_fields() {
    $arr = $GLOBALS['x'];

    $a = $arr['a'] ? $arr['a'] : [];
    $b = $arr['b'] ? [] : 'x';
    $c = $arr['c'] ? 'y' : 'z';
    // Both 'a' and 'b' should be valid here
    echo $arr['a'];
    echo $arr['b'];
    var_dump($a, $b, $c);
}

/**
 * Case 5: Mixed-typed parameter with conditional dim access
 * @param mixed $arr
 */
function test5908_mixed_param($arr) {
    $a = $arr['a'] ? $arr['a'] : [];
    $b = $arr['b'] ? [] : 'x';
    echo $arr['a'];
    var_dump($a, $b);
}
