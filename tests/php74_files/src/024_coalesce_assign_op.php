<?php

function test(string $y) : bool {
    $result = ['x' => null];
    $result['x'] ??= 2;
    $result['y'] ??= $y;
    return $result;
}
