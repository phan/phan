<?php
function test_mod_assign(int $a): void {
    $a1 = $a % 2;
    '@phan-debug-var $a1';
    $a %= 2;
    '@phan-debug-var $a';
}
