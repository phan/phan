<?php

namespace NS007;

// Test unknown type detection for variadic parameters with `mixed` type.
// @phan-file-suppress PhanUnreferencedFunction,PhanUnreferencedClass,PhanUnreferencedPublicMethod

function testFunctionWithTypeDeclaration( mixed ...$foo ): void {
    echo implode( ',', $foo );
}

/**
 * @param mixed ...$foo
 */
function testFunctionWithDocComment( ...$foo ): void {
    echo implode( ',', $foo );
}

function testTruePositive1( array ...$foo ): void {
    echo implode( ',', $foo[0] );
}

/**
 * @param string[] ...$foo
 */
function testTruePositive1__correct( array ...$foo ): void {
    echo implode( ',', $foo[0] );
}

function testTruePositive2( ...$foo ): void {
    echo implode( ',', $foo);
}

function testTruePositive2__correct( string ...$foo ): void {
    echo implode( ',', $foo);
}

class TestWithMethods {
    public function testWithTypeDeclaration( mixed ...$foo ): void {
        echo implode( ',', $foo );
    }

    /**
     * @param mixed ...$foo
     */
    public function testWithDocComment( ...$foo ): void {
        echo implode( ',', $foo );
    }

    public function testTruePositive1( array ...$foo ): void {
        echo implode( ',', $foo[0] );
    }

    /**
     * @param string[] ...$foo
     */
    public function testTruePositive1__correct( array ...$foo ): void {
        echo implode( ',', $foo[0] );
    }

    public function testTruePositive2( ...$foo ): void {
        echo implode( ',', $foo);
    }

    public function testTruePositive2__correct( string ...$foo ): void {
        echo implode( ',', $foo);
    }
}
