<?php

function msg(string $a, string $b = ''): string {
    return "$a $b";
}

// Issue #4872: array_map with unpacked arguments should not warn
// when the array elements are known to be non-empty arrays

// Test case 1: foreach mutation creating non-empty arrays
$errors = [ 'foo' => 'bar' ];
foreach ( $errors as $key => $error ) {
    $errors[$key] = (array)$error;  // Casting non-empty string to array creates non-empty array
}

$messages = array_map( function ( $err ) {
    return msg( ...$err );  // Should not warn - $err is non-empty array
}, $errors );

// Test case 2: Direct array_map creating non-empty arrays
$errors2 = array_map(function($x) { return (array)$x; }, [ 'foo' => 'bar', 'baz' => 'qux' ]);
$messages2 = array_map( function ( $err ) {
    return msg( ...$err );  // Should not warn - $err is non-empty array
}, $errors2 );

// Test case 3: Explicitly defined non-empty arrays
$errors3 = [ 'foo' => ['hello', 'world'], 'bar' => ['test'] ];
$messages3 = array_map( function ( $err ) {
    return msg( ...$err );  // Should not warn - $err is non-empty array
}, $errors3 );
