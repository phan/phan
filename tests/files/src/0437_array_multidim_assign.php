<?php

function test437() {
    $x = ['a' => ['b' => 0]];
    $x['a']['key2']['c'] = 2;
    echo strlen($x['a']['b']);
    echo strlen($x['a']['key2']['c']);
}
