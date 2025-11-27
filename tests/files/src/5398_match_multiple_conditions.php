<?php

/**
 * Test for Issue #5398: With multiple conditional expressions on 'match',
 * phan doesn't detect the return value is used in comparisons or type updates.
 *
 * When a match arm has multiple comma-separated conditions like:
 *   is_null($i), is_a($i, stdClass::class) => "A"
 * Phan should:
 * 1. Recognize that the return values of is_null() and is_a() are used (as conditions)
 * 2. Properly narrow types after each condition check
 */

// @phan-suppress-next-line PhanUnreferencedFunction
function test_match_multiple_conditions(null|int|stdClass $i): void {
    var_dump(
        match(true) {
            \is_null($i),
            \is_a($i, stdClass::class) => "A",
            default => "B"
        }
    );
}
