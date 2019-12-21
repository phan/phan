<?php

function test_offset() : array {
    $x = ['type' => null];
    $x['type'] .= 'first';
    // should infer string
    return $x['type'];
}
