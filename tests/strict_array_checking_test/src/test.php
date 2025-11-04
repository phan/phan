<?php
// Test case for strict_array_checking = true
// With strict checking enabled, PhanTypePossiblyInvalidDimOffset should be emitted
// when array shapes are accessed with potentially undefined offsets

( function ( $var ) {
    if ( !is_array( $var ) ) {
        $var = [];
    }

    if ( isset( $var['x'] ) && $var['x'] === 'a' ) {
        // Empty
    } elseif ( isset( $var['y'] ) ) {
        '@phan-debug-var $var';
        // This SHOULD warn about possibly invalid offset 'x' when strict_array_checking is true
        var_dump( $var['x'] );
    }
} )( $GLOBALS['x'] );
