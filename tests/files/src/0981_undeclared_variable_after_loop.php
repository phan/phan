<?php

// Regression test for https://github.com/phan/phan/issues/4617

( static function() {
    $emptyArr = [];
    foreach ( [ 1, 2, 3 ] as $_ ) {
        if ( rand() ) {
            continue;
        }
        $idx = 'foo';
        echo $emptyArr[$idx] ?? 'bar';
    }
} )();
