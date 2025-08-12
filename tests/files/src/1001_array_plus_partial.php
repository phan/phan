<?php

/* @phan-file-suppress PhanUnusedVariable */

function lhsGenericRHSList( array $arr ) {
    $arr += [ false ];
    '@phan-debug-var $arr';
}

function lhsGenericRHSShaped( array $arr ) {
    $arr += [ 'ans' => 42 ];
    '@phan-debug-var $arr';
}

/** @param array{first:int} $arr */
function lhsShapedRHSGeneric( array $arr, array $noShape ) {
    $arr += $noShape;
    '@phan-debug-var $arr';
}

/** @param array{first:int} $arr */
function lhsShapedRHSShaped( array $arr ) {
    $arr += [ 'second' => 'string' ];
    '@phan-debug-var $arr';
}
