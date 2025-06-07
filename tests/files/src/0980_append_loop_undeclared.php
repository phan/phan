<?php

// Regression test for https://github.com/phan/phan/issues/4885
// @phan-file-suppress PhanUnusedVariable

namespace NS980;

function testAppend( $iterable ) {
    $emptyArray = [];
    $triggerVar = '';
    foreach ( $iterable as $_ ) {
        $triggerVar .= 'foo';
        $arrayIdx = 0;
        echo $emptyArray[$arrayIdx] ?? '';
    }
}

function testNormalAssignment( $iterable ) {
    $emptyArray = [];
    foreach ( $iterable as $_ ) {
        $triggerVar = 'foo';
        $arrayIdx = 0;
        echo $emptyArray[$arrayIdx] ?? '';
    }
}
