<?php
// Tests for false positive PhanPossiblyUndeclaredVariable with try-catch-finally.
// When all catch blocks unconditionally exit (return/throw), code after the
// try-catch-finally block can only be reached if the try block succeeded,
// so variables assigned in try should be considered definitely defined.
// @see https://github.com/phan/phan/issues/5496

function test_catch_returns_with_finally(): void {
    try {
        $variable = 42;
    } catch (Exception) {
        return;
    } finally {
        // cleanup
    }
    echo $variable; // should not warn
}

function test_catch_throws_with_finally(): void {
    try {
        $variable = 42;
    } catch (Exception) {
        throw new RuntimeException('failed');
    } finally {
        // cleanup
    }
    echo $variable; // should not warn
}

function test_multiple_catches_all_exit_with_finally(): void {
    try {
        $variable = 42;
    } catch (InvalidArgumentException) {
        return;
    } catch (RuntimeException $e) {
        throw $e;
    } finally {
        // cleanup
    }
    echo $variable; // should not warn
}

// These should still warn: catch falls through

function test_catch_falls_through_with_finally(): void {
    try {
        $variable = 42;
    } catch (Exception) {
        // falls through, no exit
    } finally {
        // cleanup
    }
    echo $variable; // should warn: PhanPossiblyUndeclaredVariable
}

function test_no_catch_with_finally(): void {
    try {
        $variable = 42;
    } finally {
        // cleanup
    }
    echo $variable; // should not warn: no catch means exception propagates, so this is only reachable if try succeeded
}
