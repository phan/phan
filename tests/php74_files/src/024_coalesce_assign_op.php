<?php

function test(string $y) : bool {
    $result = ['x' => null];
    $result['x'] ??= 2;
    $result['y'] ??= $y;
    return $result;
}
$undefVar ??= 2;  // should not warn in global scope
