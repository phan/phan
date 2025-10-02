<?php

namespace NS970;

/**
 * @no-named-arguments
 */
function foo(int ...$is): array
{
    '@phan-debug-var $is';
    return [...$is];
}

foo(...['a' => 2]);
foo(...1);
