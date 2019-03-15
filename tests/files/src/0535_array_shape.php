<?php

/**
 * @param array{0:stdClass, 1:string} $v
 * @return array{0 : int, 1 : bool}
 */
function testArrayShape(array $v) : array
{
    echo $v[0];
    return [2, null];
}
testArrayShape([2, 'string']);
