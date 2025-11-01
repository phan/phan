<?php

// Test for issue #5269 - False positive PhanPossiblyUndeclaredVariable
// when a function call is involved and a later statement uses an undeclared variable

// This triggers the bug: combination of strlen() call and second if using undeclared var
( function ( string $s ) {
	if ( rand() ) {
		$n = 42;
		// FALSE POSITIVE: $n is declared on the previous line
		$possiblyUndeclared = ( $n === strlen( $s ) );
	}
	// CORRECT: $possiblyUndeclared may not be declared
	if ( $possiblyUndeclared ) {
		return;
	}
} )( 'foo' );

// Without strlen(), the false positive doesn't occur
( function ( string $s ) {
	if ( rand() ) {
		$n2 = 42;
		// This should NOT warn - $n2 is clearly declared
		$possiblyUndeclared2 = ( $n2 === 5 );
	}
	// This SHOULD warn - $possiblyUndeclared2 may not be declared
	if ( $possiblyUndeclared2 ) {
		return;
	}
} )( 'foo' );

// Without the second if, no false positive
( function ( string $s ) {
	if ( rand() ) {
		$n3 = 42;
		// This should NOT warn
		$possiblyUndeclared3 = ( $n3 === strlen( $s ) );
	}
} )( 'foo' );
