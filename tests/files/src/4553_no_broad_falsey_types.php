<?php

// Test issue #4553: Avoid introducing broad falsey types after if ($x) {} with no else/elseif

namespace NS4553;

/**
 * @param bool $param
 */
function expects_bool(bool $param): void {
    echo $param ? "true" : "false";
}

/**
 * @param 'active'|'inactive' $status
 */
function expects_active_or_inactive(string $status): void {
    echo $status;
}

class TestClass {
    /**
     * Test that a simple if ($x) {} without else doesn't introduce broad falsey types
     * @param bool $retry
     */
    public function test_no_else($retry = true): void {
        if ($retry) {
            echo "in if block";
        }
        // After if with no else, $retry should still be bool, not have broad falsey real types
        // This should NOT warn about type mismatch
        expects_bool($retry);
    }

    /**
     * Test that if/else does introduce type changes (this is expected behavior)
     * @param bool $flag
     */
    public function test_with_else($flag = true): void {
        if ($flag) {
            echo "in if block";
        } else {
            echo "in else block";
        }
        // With else, combining branches is normal behavior
        expects_bool($flag);
    }

    /**
     * Test that noreturn if block correctly narrows to falsey types
     * @param bool $value
     */
    public function test_noreturn($value = true): void {
        if ($value) {
            return; // noreturn - always exits
        }
        // After noreturn if block, $value should be false
        // This demonstrates that noreturn case still works correctly
        if ($value) {
            echo "This should be impossible";  // Would warn if type narrowing didn't work
        }
    }

    /**
     * Test the original issue #4550 scenario - recursive call with bool parameter
     * @param \stdClass $database
     * @param bool $retry
     */
    public function generateNewId($database, $retry = true): void {
        if ($retry) {
            $id = $this->generateNewId($database, false);
        }

        if (\mt_rand(0, 1)) {
            // This should NOT warn - $retry should be bool without overly broad falsey real types
            $id = $this->generateNewId($database, $retry);
        }
    }

    /**
     * Test with elseif - should still narrow types
     * @param string $status
     */
    public function test_elseif($status): void {
        if ($status === 'active') {
            echo "active";
        } elseif ($status === 'pending') {
            echo "pending";
        }
        // With elseif, type narrowing should work - $status is not 'active' and not 'pending'
        // This should warn because $status could be many things
        expects_active_or_inactive($status);
    }

    /**
     * Test multiple if statements in sequence without else
     * @param bool $flag1
     * @param bool $flag2
     */
    public function test_multiple_sequential_ifs($flag1 = true, $flag2 = true): void {
        if ($flag1) {
            echo "flag1 is truthy";
        }
        // First if had no else, so $flag1 should still be bool
        expects_bool($flag1);

        if ($flag2) {
            echo "flag2 is truthy";
        }
        // Second if had no else, so $flag2 should still be bool
        expects_bool($flag2);
    }
}
