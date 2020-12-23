<?php
function test_loop1(array $vals) {
    foreach ($vals as $val) {
        // TODO: Fix PhanUndeclaredVariable
        echo $previousVal ?? 'default';

        $previousVal = $val;
    }
}

function test_loop2(array $vals) {
    foreach ($vals as $val) {
        // TODO: Fix PhanUndeclaredVariable
        echo $previousVal ?? 'default';

        $previousVal = $val + 1;  // should emit PhanUndeclaredVariable
        $previousVal = $val;  // should not warn
    }
}
