<?php
function test_increment(int $x, int $y) {
    --$y;
    --$y;
    $x++;
    $z;
    $z = 2;
    echo $z;
}
test_increment(2, 3);

function test_loop119(int $other, int $n, int $j) {
    for ($i = 0;
         $other < $n;
         $i++, $j++) {
        echo $other;
    }
}
test_loop119(0, 0, 0);

function test_loop119b(int $j) {
    for ($i = 0; $i < 10; $i++) {
        $j += 4;
    }
}
