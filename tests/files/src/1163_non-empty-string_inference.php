<?php

namespace NS1163;

function testInequalities( string $a, string $b ) {
    if ( $a !== '' ) {
        '@phan-debug-var $a';
    }
    if ( $b != '' ) {
        '@phan-debug-var $b';
    }
}
