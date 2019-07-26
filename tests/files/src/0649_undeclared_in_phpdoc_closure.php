<?php

namespace CheckUndeclared;

use Closure;

/**
 * summary
 * @param Closure(Undeclared,bool):mixed $cb
 * @return Closure(Undeclared):(UndeclaredClass2)
 * @throws callable(Undeclared3):void
 */
function test($cb, $x) {
    return $cb($x, true);
}

class Foo {
    /** @var Closure(Undeclared,bool):mixed description */
    public $prop;
}
