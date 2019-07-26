<?php

/**
 * @param 'ax'|'bx' $x
 */
function expect_ab($x) {
    var_export($x);
}

expect_ab(sprintf(rand(0, 2) ? 'a%s' : 'b%s', 'x'));
expect_ab(sprintf(rand(0, 2) ? '%sa' : '%sb', 'x'));
