<?php

function testTrueLoops(string $s) {
    for ($i = 0; true; ) {
        $i = $s;
        break;
    }
    echo intdiv($i, 2);
    assert(is_int($i));
    for ($j = 0; ; ) {
        $j = $s;
        if (rand() % 2 == 1) {
            break;
        }
    }
    echo intdiv($j, 2);
    assert(is_int($j));
    $k = 1;
    while (true) {
        $k = $s;
        break;
    }
    echo intdiv($k, 2);
    assert(is_int($k));
}
