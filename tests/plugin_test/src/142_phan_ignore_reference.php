<?php
// The @phan-ignore-reference comment makes phan ignore the effect of references on the inferred union type of variables.

/**
 * @param string &$fmt @phan-ignore-reference
 * @param string &$fmt2
 */
if (!function_exists('prepend_format_string')) {
// The compile-time warning about passing non-variable to a reference (for known functions) stopped being a compile-time warning in php 8.
function prepend_format_string(string &$fmt, string &$fmt2) {
    $fmt = 'INFO: ' . $fmt;
    $fmt2 = 'INFO: ' . $fmt2;
}
}

function test_ignore_reference() {
    $fmt = 'Hello, %s!';
    $fmt2 = 'Bye, %s!';
    prepend_format_string($fmt, $fmt2);
    printf($fmt, 'Hello', 'World');
    printf($fmt2, 'Hello', 'World');
    // Should warn about non-references.
    prepend_format_string(null, null);
}
test_ignore_reference();
