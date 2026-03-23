<?php

/**
 * Test that plugin closures receive correctly normalized arguments
 * when named arguments are used in non-declaration order.
 * @see https://github.com/phan/phan/issues/5483
 */

// Test MiscParamPlugin: checks $args[0] (needle) and $args[1] (haystack) by position
function test_in_array_named_args(): void {
    // Positional - impossible check (string needle in int array)
    in_array('x', [1, 2, 3], true);

    // Named args in declaration order
    in_array(needle: 'x', haystack: [1, 2, 3], strict: true);

    // Named args reversed - should still detect impossible check
    in_array(strict: true, haystack: [1, 2, 3], needle: 'x');

    // Named args - valid check (int needle in int array) - no warning
    in_array(strict: true, haystack: [1, 2, 3], needle: 1);
}
