<?php

function test_loop_var_return() {
    $index = 3;
    foreach ([2,3] as $i => $x) {
        if ($x % 2 > 0) {
            if (rand() % 2 > 0) {
                return $index;
            }
        }
        $myUnusedVariable = 2;
        $index = $i;  // should not warn, it's used in `return $index`
    }
}
test_loop_var_return();
