<?php

// Test simple variable-to-variable reference
function test_simple_ref() {
    $var1 = 42;
    '@phan-debug-var $var1'; // Should show 42(real=42)
    $var2 =& $var1;
    '@phan-debug-var $var1'; // Should show int(real=int) - real type erased
    '@phan-debug-var $var2'; // Should show int(real=int) - real type erased
    $var2 = 17;
    '@phan-debug-var $var1'; // Should show int(real=int) - still no real type
    '@phan-debug-var $var2'; // Should show int(real=int) - still no real type
}

// Test that non-referenced variables still track literal types
function test_no_ref() {
    $x = 42;
    '@phan-debug-var $x'; // Should show 42(real=42)
    $x = 17;
    '@phan-debug-var $x'; // Should show 17(real=17)
}

// Test reference in conditional
function test_conditional_ref() {
    $x = 10;
    $y = 20;
    '@phan-debug-var $x'; // Should show 10(real=10)
    '@phan-debug-var $y'; // Should show 20(real=20)
    if (random_int(0, 1)) {
        $ref =& $x;
    } else {
        $ref =& $y;
    }
    '@phan-debug-var $x'; // Should show int(real=int) - real type erased
    '@phan-debug-var $y'; // Should show int(real=int) - real type erased
    '@phan-debug-var $ref'; // Should show int(real=int)
    $ref = 99;
    '@phan-debug-var $x'; // Should still show int(real=int)
    '@phan-debug-var $y'; // Should still show int(real=int)
}

// Test string literals
function test_string_ref() {
    $str1 = 'hello';
    '@phan-debug-var $str1'; // Should show 'hello'(real='hello')
    $str2 =& $str1;
    '@phan-debug-var $str1'; // Should show string(real=string)
    '@phan-debug-var $str2'; // Should show string(real=string)
    $str2 = 'world';
    '@phan-debug-var $str1'; // Should show string(real=string)
    '@phan-debug-var $str2'; // Should show string(real=string)
}

// Test float literals
function test_float_ref() {
    $f1 = 3.14;
    '@phan-debug-var $f1'; // Should show 3.14(real=3.14)
    $f2 =& $f1;
    '@phan-debug-var $f1'; // Should show float(real=float)
    '@phan-debug-var $f2'; // Should show float(real=float)
    $f2 = 2.71;
    '@phan-debug-var $f1'; // Should show float(real=float)
    '@phan-debug-var $f2'; // Should show float(real=float)
}

// Test that subsequent non-reference assignments keep the flag
function test_ref_persists() {
    $a = 1;
    $b =& $a;
    '@phan-debug-var $a'; // Should show int(real=int)
    $c = $a; // Regular assignment from a referenced variable
    '@phan-debug-var $c'; // Should show int(real=int) - gets the value from $a
    $a = 5; // Assign to referenced variable
    '@phan-debug-var $a'; // Should still show int(real=int) - flag persists
}

// Test reference chain
function test_ref_chain() {
    $x = 100;
    $y =& $x;
    '@phan-debug-var $x'; // Should show int(real=int)
    '@phan-debug-var $y'; // Should show int(real=int)
    $z =& $y;
    '@phan-debug-var $x'; // Should show int(real=int)
    '@phan-debug-var $y'; // Should show int(real=int)
    '@phan-debug-var $z'; // Should show int(real=int)
}

// Test reference with parameter passing
function modify_ref(&$param) {
    $param = 42;
}

function test_ref_with_function_param() {
    $a = 10;
    $b =& $a;
    '@phan-debug-var $a'; // Should show int(real=int)
    '@phan-debug-var $b'; // Should show int(real=int)

    modify_ref($a);  // This should not reintroduce literal types
    '@phan-debug-var $a'; // Should still show int (no literal type)
    '@phan-debug-var $b'; // Should still show int(real=int)
}

// Test reference to undefined variable
function test_undefined_ref() {
    $ref =& $x;  // Reference to undefined variable
    '@phan-debug-var $x'; // Should show empty type initially

    $x = 42;     // Now assign to $x
    '@phan-debug-var $x'; // Should show int(real=int), not 42(real=42)
    '@phan-debug-var $ref'; // Should show int(real=int)
}

// Test existing variable becoming a reference (issue #4354 review #3)
function test_existing_var_becomes_ref() {
    $b = 17;
    '@phan-debug-var $b'; // Should show 17(real=17)

    $b =& $a;  // Existing $b becomes reference to undefined $a
    '@phan-debug-var $b'; // Should show empty type since $a is undefined
    '@phan-debug-var $a'; // Should show empty type

    $b = 99;   // Assign through the reference
    '@phan-debug-var $b'; // Should show int(real=int), NOT 99(real=99) - literal erased

    $a = 42;   // Assign to the other reference
    '@phan-debug-var $a'; // Should show int(real=int), NOT 42(real=42) - literal erased
}

// Test both variables exist before reference
function test_both_exist_before_ref() {
    $a = 10;
    $b = 20;
    '@phan-debug-var $a'; // Should show 10(real=10)
    '@phan-debug-var $b'; // Should show 20(real=20)

    $b =& $a;  // $b becomes reference to $a
    '@phan-debug-var $a'; // Should show int(real=int) - literal erased
    '@phan-debug-var $b'; // Should show int(real=int) - literal erased

    $a = 42;
    '@phan-debug-var $a'; // Should show int(real=int), NOT 42(real=42)

    $b = 99;
    '@phan-debug-var $b'; // Should show int(real=int), NOT 99(real=99)
}
