<?php
if (rand(0, 100) > 50) {
    $a = 2;
} else {
    $a = 3;
}

function f($v) {
    return $v;
}

print f($a) . "\n";
