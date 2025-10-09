<?php
function issue4629_func() {
    $arr = [0, 1];
    return $arr[CONSTANT_NAME];
}

$m = 1;
define('CONSTANT_NAME', $m);

echo issue4629_func();
