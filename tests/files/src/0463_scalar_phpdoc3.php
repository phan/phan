<?php

/**
 * Support phpdoc3 alpha's scalar as int|float|bool|string , corresponding to what is_scalar would expect (https://secure.php.net/is_scalar)
 *
 * @param ?scalar $x
 * @return scalar
 */
function example_scalar($x) {
    if ($x) {
        return $x;
    }
    return null;
}

example_scalar(2);
example_scalar('x');
example_scalar(false);
example_scalar(true);
example_scalar(rand(1,0) == 0);
example_scalar(2.5);
example_scalar(new stdClass());
example_scalar([]);
example_scalar(null);
