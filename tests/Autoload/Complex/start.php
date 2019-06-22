<?php

namespace Tests\Autoload\Complex;

/**
 * Test
 */
function foo(): A {
    return new A(false);
}

$a = foo();
$a->foo(new E, new F);
