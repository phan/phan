<?php

/* @phan-file-suppress PhanUnusedVariable */

namespace NS1161;

function testFuncGetArgs($req1, $req2) {
    $d = func_get_args();
    '@phan-debug-var $d';
}

function testFuncGetArgs__potentiallyEmpty($opt = 42) {
    $d = func_get_args();
    '@phan-debug-var $d';
}

function testNestedTruthinessCheck( array $data ) {
    if ( is_array( $data['label'] ) && $data['label'] ) {
        $x = $data['label'];
        '@phan-debug-var $x';
    }
}

/**
 * @param non-empty-array[] $data
 */
function testArrayMap( array $data ) {
    array_map( function ( $el ) {
        '@phan-debug-var $el'; // TODO: Ideally, phan would infer `non-empty-array` here, but we don't infer closure param types
    }, $data );
}
