<?php

namespace Tests\Autoload\Simple;

/**
 * Test
 */
function foo(): A {
    return new A(true);
}

foo();
