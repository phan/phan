<?php

/* @phan-file-suppress PhanUnusedVariable */

function unaryFloatInt(float|int $value): void {
    '@phan-debug-var $value';
    $minus = -$value;
    '@phan-debug-var $minus';
    $plus = +$value;
    '@phan-debug-var $plus';
}

function unaryIntFloat(int|float $value): void {
    '@phan-debug-var $value';
    $minus = -$value;
    '@phan-debug-var $minus';
    $plus = +$value;
    '@phan-debug-var $plus';
}
