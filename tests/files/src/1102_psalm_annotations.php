<?php

/**
 * @psalm-param int $value
 */
function takes_int($value)
{
}

takes_int('not an int');

/**
 * @psalm-template T of Countable
 * @psalm-param T $countable
 * @psalm-return T
 */
function require_countable($countable)
{
    return $countable;
}

require_countable(new ArrayObject());
require_countable(new stdClass());
