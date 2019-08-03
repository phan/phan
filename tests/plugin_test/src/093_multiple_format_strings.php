<?php

/**
 * @param 'ax'|'bx' $x
 */
function expect_ab($x) {
    var_export($x);
}

expect_ab(sprintf(rand(0, 2) ? 'a%s' : 'b%s', 'x'));
expect_ab(sprintf(rand(0, 2) ? '%sa' : '%sb', 'x'));

// Phan will be able to check for too many arguments if the conversion specifiers of the format string are identical.
// It currently does not check if format strings are compatible.
printf(rand(0, 2) ? 'The argument is %s' : 'The arguments are %s', 'first', 'extra');
