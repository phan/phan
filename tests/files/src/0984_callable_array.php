<?php

/* @phan-file-suppress PhanUnusedVariable */

namespace NS984;

/** @param callable-array $callable */
function takesCallable( $callable ) {
    $first = $callable[0];
    '@phan-debug-var $first';
    $second = $callable[1];
    '@phan-debug-var $second';

    $reset = reset( $callable );
    '@phan-debug-var $reset';
    $key = key( $callable );
    '@phan-debug-var $key';

    foreach( $callable as $k => $v ) {
        '@phan-debug-var $k, $v';
        var_dump( $k, $v );
    }
}
