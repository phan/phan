<?php
function test_foreach() {
    $x = [];
    $y = rand() ? [] : null;
    $z = rand() ? [] : STDIN;
    foreach ($x as $a) {echo $a;}
    foreach ($y as $a) {echo $a;}
    foreach ($z as $a) {echo $a;}
    foreach ([] as $a) {echo $a;}
}
function test_foreach_generator() {
    $x = [];
    $y = rand() ? [] : null;
    $z = rand() ? [] : STDIN;
    yield from 0;
    yield from $x;
    yield from $y;
    yield from $z;
}
