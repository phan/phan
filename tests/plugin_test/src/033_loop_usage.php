<?php
function example_loop() {
    $a = 2;
    $b = 3;
    foreach ([2,3] as $x) {
        if ($a % 2 > 0) {
            echo "odd\n";
        }
        $a = $a + $x;
        $b = $a;
    }
}
function example_loop2() {
    foreach ([2,3] as $x) {
        $a = $x;
        $a = 2 + $x;
        echo $a;
    }
}
example_loop();
example_loop2();
