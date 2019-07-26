<?php
function test696() {
    $a = 2;
    $b = 3;
    if (rand() % 2 > 0) {
        $c = $b;
    } else {
        $c = $a;
    }
    echo $c;
    assert(is_int($c));
}
