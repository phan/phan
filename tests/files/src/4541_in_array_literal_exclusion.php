<?php

// Test narrowing literal types after !in_array with strict comparison
// This implements the feature requested in https://github.com/phan/phan/issues/4541

namespace NS4541;

/**
 * @param 'alpha'|'beta' $param
 */
function expects_alpha_or_beta(string $param): void {
    echo $param;
}

/**
 * @param 1|2 $num
 */
function expects_one_or_two(int $num): void {
    echo $num;
}

// Test string literal exclusion
function test_string_literal_exclusion(string $x): void {
    if ($x === 'alpha' || $x === 'beta' || $x === 'gamma') {
        if (!in_array($x, ['gamma'], true)) {
            // After excluding 'gamma', $x should be 'alpha'|'beta'
            expects_alpha_or_beta($x);  // Should not warn
        }
    }
}

// Test int literal exclusion
function test_int_literal_exclusion(int $value): void {
    if ($value === 1 || $value === 2 || $value === 3) {
        if (!in_array($value, [3], true)) {
            // After excluding 3, $value should be 1|2
            expects_one_or_two($value);  // Should not warn
        }
    }
}

// Test multiple exclusions
function test_multiple_exclusions(string $status): void {
    if ($status === 'new' || $status === 'active' || $status === 'inactive' || $status === 'deleted') {
        if (!in_array($status, ['deleted', 'inactive'], true)) {
            // After excluding 'deleted' and 'inactive', $status should be 'new'|'active'
            // This demonstrates the feature working with multiple values to exclude
            if ($status === 'deleted') {
                // Type mismatch - $status is 'new'|'active', not 'deleted'
                echo "bad";
            }
        }
    }
}

// Test that non-strict comparison is NOT narrowed (for safety)
function test_non_strict_not_narrowed(string $status): void {
    if ($status === 'active' || $status === 'inactive') {
        if (!in_array($status, ['active'], false)) {
            // Should NOT narrow because strict=false is unsafe
            // Both values still possible due to type coercion
            expects_alpha_or_beta($status);  // Should warn - still could be 'active' or 'inactive'
        }
    }
}

// Test that non-literal arrays are NOT processed
function test_non_literal_array(string $x, array $values): void {
    if ($x === 'alpha' || $x === 'beta') {
        if (!in_array($x, $values, true)) {
            // Should NOT narrow - $values is not a literal array
            expects_alpha_or_beta($x);  // Should not warn - $x is still 'alpha'|'beta' (not narrowed)
        }
    }
}

// Test with mixed type
function test_mixed_type($value): void {
    if ($value === 'alpha' || $value === 'beta' || $value === 'gamma') {
        if (!in_array($value, ['gamma'], true)) {
            // Works with mixed type too
            expects_alpha_or_beta($value);  // Should not warn
        }
    }
}
