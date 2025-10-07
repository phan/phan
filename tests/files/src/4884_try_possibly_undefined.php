<?php

/**
 * Test fixtures covering issue #4884.
 *
 * @phan-file-suppress PhanPluginNoCommentOnFunction
 * @phan-file-suppress PhanPluginUnknownFunctionReturnType
 * @phan-file-suppress PhanThrowTypeAbsent
 * @phan-file-suppress PhanUnusedVariableCaughtException
 * @phan-file-suppress PhanPluginRemoveDebugEcho
 * @phan-file-suppress PhanPluginEmptyStatementTryFinally
 */

function mayThrow() {
    if (rand()) {
        throw new Exception;
    }
    return 42;
}

// Test case 1: Variable defined in try, used after try-catch
// Should warn: variable is possibly undefined
function test1() {
    try {
        $val = mayThrow();
    } catch (Exception $e) {
    }
    echo $val;  // PhanPossiblyUndeclaredVariable
}

// Test case 2: Variable defined in try, all catches exit
// Should NOT warn: variable is guaranteed to be defined
function test2() {
    try {
        $val2 = mayThrow();
    } catch (Exception $e) {
        return;  // All catch blocks exit
    }
    echo $val2;  // No warning - $val2 is definitely defined
}

// Test case 3: Variable defined before try
// Should NOT warn: variable is already defined
function test3() {
    $val3 = 0;
    try {
        $val3 = mayThrow();
    } catch (Exception $e) {
    }
    echo $val3;  // No warning - $val3 was defined before try
}

// Test case 4: try with finally block
// Should warn: variable is possibly undefined (finally doesn't guarantee try completed)
function test4() {
    try {
        $val4 = mayThrow();
    } catch (Exception $e) {
    } finally {
    }
    echo $val4;  // PhanPossiblyUndeclaredVariable
}

// Test case 5: Inline conditional throw
// Should warn: variable is possibly undefined
function test5() {
    try {
        if (rand()) {
            throw new Exception();
        }
        $val5 = 42;
    } catch (Exception $e) {
    }
    echo $val5;  // PhanPossiblyUndeclaredVariable
}

// Test case 6: Multiple catch blocks, some exit
// Should warn: at least one catch can fall through
function test6() {
    try {
        $val6 = mayThrow();
    } catch (RuntimeException $e) {
        return;  // This one exits
    } catch (Exception $e) {
        // This one doesn't exit
    }
    echo $val6;  // PhanPossiblyUndeclaredVariable
}

// Test case 7: No catch blocks
// Should warn: exception will propagate
function test7() {
    try {
        $val7 = mayThrow();
    } finally {
    }
    echo $val7;  // PhanPossiblyUndeclaredVariable
}
