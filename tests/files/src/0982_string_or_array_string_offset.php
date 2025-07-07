<?php

// Regression test for https://github.com/phan/phan/issues/4709

namespace NS982;

/** @param string|array{a:int} $x */
function arrayOrString( $x ) {
    var_export($x[3]);
    var_export($x['missing']);
}

/** @param int|array{a:int} $y */
function arrayOrInt( $y ) {
    var_export($y[3]);
    var_export($y['missing']);
}

// The following would need stricter checks to emit issues
/** @param string|array{a:int} $x */
function arrayOrStringOffsetExists( $x ) {
    var_export($x[3]);
    var_export($x['a']);
}

/** @param int|array{a:int} $y */
function arrayOrIntOffsetExists( $y ) {
    var_export($y[3]);
    var_export($y['a']);
}
