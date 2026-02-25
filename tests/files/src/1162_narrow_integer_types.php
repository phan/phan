<?php

namespace NS1162;

function testInequality( int $x, int $y, bool $z ): void {
    if ( $x !== 0 ) {
        '@phan-debug-var $x';
    }
    if ( $y != 0 ) {
        '@phan-debug-var $y';
    }
    if ( $z != 0 ) {
        // Make sure this still removes `false` as a possible type.
        '@phan-debug-var $z';
    }
}

function testZeroRHS( int $a, int $b, int $c, int $d ) {
    if ( $a > 0 ) {
        '@phan-debug-var $a';
    }
    if ( $b >= 0 ) {
        '@phan-debug-var $b';
    }
    if ( $c < 0 ) {
        '@phan-debug-var $c';
    }
    if ( $d <= 0 ) {
        '@phan-debug-var $d';
    }
}

function testPositiveRHS( int $a, int $b, int $c, int $d ) {
    if ( $a > 42 ) {
        '@phan-debug-var $a';
    }
    if ( $b >= 42 ) {
        '@phan-debug-var $b';
    }
    if ( $c < 42 ) {
        '@phan-debug-var $c';
    }
    if ( $d <= 42 ) {
        '@phan-debug-var $d';
    }
}

function testNegativeRHS( int $a, int $b, int $c, int $d ) {
    if ( $a > -42 ) {
        '@phan-debug-var $a';
    }
    if ( $b >= -42 ) {
        '@phan-debug-var $b';
    }
    if ( $c < -42 ) {
        '@phan-debug-var $c';
    }
    if ( $d <= -42 ) {
        '@phan-debug-var $d';
    }
}
