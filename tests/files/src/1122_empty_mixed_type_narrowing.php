<?php

/**
 * Test that empty() on mixed type doesn't incorrectly narrow to null
 * @see https://github.com/phan/phan/issues/5074
 */


function test_empty_mixed(mixed $x) : bool {
    if (empty($x)) {
        // After empty($x), $x could be null, false, 0, "", "0", 0.0, or []
        // It should NOT be narrowed to just null

        if (is_int($x) === false) {
            // This should NOT produce "Impossible attempt to cast $x of type null to int"
            // because $x could be 0 (which is int and empty)
        }

        if ($x !== "0") {
            // This should NOT produce "Suspicious attempt to compare $x of type null to '0'"
            // because $x could be "0" (which is empty)
        }
    }
    return true;
}

function test_empty_mixed_with_checks(mixed $x) : void {
    if(
        empty($x) &&
        is_int($x) === false &&
        $x !== "0"
    ){
        // This combination is valid - $x could be null, false, "", 0.0, or []
        var_dump($x);
    }
}

// Additional test cases
function test_empty_nullable_mixed(?mixed $x) : void {
    if (empty($x)) {
        // Same as above - should not narrow to just null
        if (is_string($x)) {
            // Valid - could be "" or "0"
            echo "Empty string: $x\n";
        }
    }
}

function test_not_empty_mixed(mixed $x) : void {
    if (!empty($x)) {
        // $x is truthy but still mixed
        '@phan-debug-var $x';
        if ($x === 0) {
            // Should warn - 0 is falsey
            echo "Should not reach here\n";
        }
    }
}
