<?php

// Case 1: Simple reassignment without use - initial assignment is wasted
function test_simple_reassignment() {
    $a = 5;      // Should warn: assigned but overwritten before use
    $a += 1;     // Should warn: result never used
}

// Case 2: Multiple reassignments - all but last are wasted
function test_multiple_reassignments() {
    $a = 5;      // Should warn
    $a = 10;     // Should warn
    $a = 15;     // OK - value is used below
    echo $a;
}

// Case 3: Compound assignment in expression context - SHOULD warn for initial
function test_compound_in_expression() {
    $a = 5;      // Should warn - value not meaningfully used
    $a += 1;     // OK - result is used below
    echo $a;
}

// Case 3b: Compound assignment result used in expression - initial OK, final warns
function test_compound_result_used() {
    $a = 5;      // OK - used in the += operation's calculation
    echo ($a += 1);  // Should warn - the result ($a = 6) is never used after echo
}

// Case 4: Compound then simple assignment
function test_compound_then_simple() {
    $a = 5;      // Should warn
    $a += 1;     // Should warn if not used
    $a = 20;     // OK - used below
    echo $a;
}

// Case 5: String concatenation
function test_string_concatenation() {
    $str = 'prefix';  // Should warn
    $str .= 'And';    // Should warn
    $str .= 'Suffix'; // OK - used below
    echo $str;
}

// Case 6: Only final assignment used
function test_only_final_used() {
    $x = 1;  // Should warn
    $x = 2;  // Should warn
    $x = 3;  // OK
    return $x;
}

// Case 7: Multiplication without use
function test_multiplication_unused() {
    $n = 10;   // Should warn
    $n *= 2;   // Should warn - result never used
}

// Case 8: Mixed operations
function test_mixed_operations() {
    $val = 100;    // Should warn
    $val -= 50;    // Should warn
    $val *= 2;     // Should warn
    $val = 0;      // Should warn - never used
}
