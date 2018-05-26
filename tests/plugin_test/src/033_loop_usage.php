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
example_loop();
