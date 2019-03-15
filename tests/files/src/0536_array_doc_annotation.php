<?php

/**
 * @param (int|string)[] $values
 * @return (int|string)[]
 */
function intAndStrings($values)
{
    var_export($values);
    return [
        1,
        'x',
        false,
    ];
}
intAndStrings([2]);
intAndStrings([[]]);
