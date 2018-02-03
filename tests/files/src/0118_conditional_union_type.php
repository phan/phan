<?php
$v = [];
if (rand() % 2 > 0) {
    try {
        $v = 'string';
    } catch (Exception $e) {
        $v = false;
    }
} else {
    $v = false;
}
function f(int $p) {}  // type should be inferred as string|false. The original array type no longer exists.
f($v);
