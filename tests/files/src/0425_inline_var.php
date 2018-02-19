<?php

/** @param mixed $x */
function test($x) : int {
    '@phan-var int $x';

    echo strlen($x);
    return $x;
}
