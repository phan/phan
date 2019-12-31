<?php
function test_loop(array $x) : bool {
    $is_valid = true;
    foreach ($x as $y) {
        if ($is_valid) {
            if (!$y) {
                $is_valid = false;
            }
        }
        echo "Inside loop: "; '@phan-debug-var $is_valid';
    }
    echo "After loop: "; '@phan-debug-var $is_valid';
    return $is_valid;
}

function test_loop_truthy(array $x) : bool {
    $is_valid = false;
    foreach ($x as $y) {
        if (!$is_valid) {
            $is_valid = $y > 0;
        }
        echo "Inside loop: "; '@phan-debug-var $is_valid';
    }
    echo "After loop: "; '@phan-debug-var $is_valid';
    return $is_valid;
}
