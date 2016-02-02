<?php
$v = [];
if (true) {
    try {
        $v = 'string';
    } catch (Exception $e) {
        $v = false;
    }
} else {
    $v = false;
}
function f(int $p) {}
f($v);
