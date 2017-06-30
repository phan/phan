<?php

function test326() {
    sort([]);
    $x = 2;
    sort($x);
    $z = 'a';
    $y = array_shift($z);
}
