<?php

/**
 * @deprecated
 */
function deprecated_func() {
}

/**
 * @param callable $cb
 */
function user_callback($cb) {
    $cb();
}

/**
 * @param callable $fn
 */
function another_callback($fn) {
}

// Should detect deprecated function usage
deprecated_func();                           // Line 23: Direct call
call_user_func('deprecated_func');          // Line 24: call_user_func
user_callback('deprecated_func');           // Line 25: User callback
another_callback('deprecated_func');        // Line 26: Another callback
array_filter([1, 2, 3], 'deprecated_func'); // Line 27: array_filter
