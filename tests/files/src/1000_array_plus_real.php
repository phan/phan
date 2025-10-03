<?php

/** @phan-file-suppress PhanUnusedVariable */

namespace NS1000;

/**
 * @param array{key:bool} $arg
 */
function arrayPlusShape( $arg ) {
    $realRHS = $arg + [ 'key' => 'string' ];
    '@phan-debug-var $realRHS';

    $realLHS = [ 'key' => 'string' ] + $arg;
    '@phan-debug-var $realLHS';
}

/**
 * @param array $fromPHPDoc
 */
function literalAndGeneric( $fromPHPDoc, array $real ) {
    // None of the results below should have a shaped real type

    $lhsLiteralRhsDoc = [ 'key' => 'string' ] + $fromPHPDoc;
    '@phan-debug-var $lhsLiteralRhsDoc';

    $lhsLiteralRhsReal = [ 'key' => 'string' ] + $real;
    '@phan-debug-var $lhsLiteralRhsReal';

    $lhsDocRhsLiteral = $fromPHPDoc + [ 'key' => 'string' ];
    '@phan-debug-var $lhsDocRhsLiteral';

    $lhsRealRhsLiteral = $real + [ 'key' => 'string' ];
    '@phan-debug-var $lhsRealRhsLiteral';
}

/**
 * @param array{knownkey:bool,otherkey:bool} $arg
 */
function arrayPlusPartial( array $arg ) {
    if ( is_bool( $arg['knownkey'] ) ) {
        '@phan-debug-var $arg'; // Confirm that this now has a real type

        $withoutFullRealShape = $arg + [ 'otherkey' => 'string' ];
        '@phan-debug-var $withoutFullRealShape'; // This must NOT have `otherkey:'string'` in the real type.
    }
}

function arrayPlusAllKnown() {
    $res = [ 'left' => true, 'both' => true ] + [ 'both' => 'string', 'right' => 'string' ];
    '@phan-debug-var $res'; // Real and non-real types should be the same
}
