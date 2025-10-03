<?php

/**
 * Test case for issue #5074 - empty() on mixed type incorrectly narrowing to null
 * @see https://github.com/phan/phan/issues/5074
 */

function x(mixed $x) : bool {
    if(
        empty($x) &&
        is_int($x) === false &&
        $x !== "0"
    ){
        return false;
    }
    return true;
}

// This should work without warnings
var_dump(x(0));      // returns false
var_dump(x(null));   // returns false
var_dump(x("0"));    // returns true
var_dump(x(""));     // returns false
var_dump(x(false));  // returns false
var_dump(x([]));     // returns false
var_dump(x(1));      // returns true