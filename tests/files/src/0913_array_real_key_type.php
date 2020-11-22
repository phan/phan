<?php
/** @return array<int,int> */
function test_int_key(int $i, array $values): array {
    $x = [$_GET['foo'] => 'something'];
    '@phan-debug-var $x';
    foreach ($x as $i => $v) {
        '@phan-debug-var $i';
        var_export([$i, $v]);
    }
    $y = [$i => $i];
    '@phan-debug-var $y';
    return $y;
}
