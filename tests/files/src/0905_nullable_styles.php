<?php

// Test different nullability styles representing the same type

/** @param ?string|?int $foo */
function test1(string|int|null $foo) {
    echo $foo;
}

/** @param string|int|null $foo */
function test2(string|int|null $foo) {
    echo $foo;
}

/** @param int|null $foo */
function test3(?int $foo) {
    echo $foo;
}

/** @param string|int|null $foo */
function test4(string|int|null $foo) {
    echo $foo;
}

// Closure test from original issue
/** @param ?string|?int $foo */
( function(string|int|null $foo) {
    echo $foo;
} )( 42 );

// This should still warn - incompatible types
/** @param ?string $bar */
function test5(int $bar) {
    echo $bar;
}

// This should still warn - incompatible types
/** @param string|int $baz */
function test6(?float $baz) {
    echo $baz;
}
