<?php

class TestNeverReturnTypeNarrowing {
    public static function logError(string $err = ""): never {
        echo $err;
        exit(1);
    }

    public static function throwException(string $message = ""): never {
        throw new RuntimeException($message);
    }

    public function expectString(string $arg): void {
        echo "Got string: $arg";
    }

    public function expectNonNull(mixed $arg): void {
        echo "Got non-null: " . (string)$arg;
    }

    // Test case 1: Basic empty() check with never return - the exact scenario from PR #5078
    public function testEmptyWithNeverReturn(?string $arg): void {
        if (empty($arg)) {
            self::logError("arg is empty");
        }
        // After the never-returning branch, $arg should be narrowed to non-empty string
        $this->expectString($arg); // Should not warn - $arg is now string (non-empty)
    }

    // Test case 2: isset() check with never return
    public function testIssetWithNeverReturn(?string $arg): void {
        if (!isset($arg)) {
            self::throwException("arg is not set");
        }
        // After the never-returning branch, $arg should be narrowed to string (non-null)
        $this->expectString($arg); // Should not warn - $arg is now string
    }

    // Test case 3: is_null() check with never return
    public function testIsNullWithNeverReturn(?int $value): void {
        if (is_null($value)) {
            self::logError("value is null");
        }
        // After the never-returning branch, $value should be narrowed to int (non-null)
        $this->expectNonNull($value); // Should not warn - $value is now int
    }

    // Test case 4: Multiple conditions with never return
    public function testMultipleConditionsWithNeverReturn(?string $arg1, ?string $arg2): void {
        if (empty($arg1) || empty($arg2)) {
            self::throwException("one or both args are empty");
        }
        // After the never-returning branch, both args should be narrowed to non-empty strings
        $this->expectString($arg1); // Should not warn
        $this->expectString($arg2); // Should not warn
    }

    // Test case 5: Nested if statements with never return
    public function testNestedIfWithNeverReturn(?string $outer, ?int $inner): void {
        if (empty($outer)) {
            if (is_null($inner)) {
                self::logError("both are problematic");
            }
            // Even in nested context, never return should work
            self::throwException("outer is empty");
        }
        // After the never-returning branch, $outer should be narrowed to non-empty string
        $this->expectString($outer); // Should not warn
        // $inner is still potentially null here since the inner condition wasn't the path taken
    }

    // Test case 6: Complex boolean conditions
    public function testComplexConditionsWithNeverReturn(?string $arg): void {
        if ($arg === null || $arg === "") {
            self::logError("arg is null or empty");
        }
        // After the never-returning branch, $arg should be narrowed to non-empty string
        $this->expectString($arg); // Should not warn
    }

    // Test case 7: Ensure false positives aren't introduced - case where narrowing shouldn't happen
    public function testNoNarrowingWithoutNeverReturn(?string $arg): void {
        if (empty($arg)) {
            echo "arg is empty, but not exiting";
            return; // This is NOT never return
        }
        // $arg should still be narrowed here since empty branch returned
        $this->expectString($arg); // Should not warn
    }
}