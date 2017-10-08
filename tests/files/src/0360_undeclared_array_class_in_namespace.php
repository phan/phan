<?php

namespace Foo\Bar;

use Foo\Baz\MissingClass;
use Foo\Bat\OtherMissingClass;

/**
 * @param MissingClass[][][] $x
 * @return OtherMissingClass[][]
 */
function myMethod($x) : array {
    return [];
}

class MyClass {
    /** @var Closure[][] should warn */
    public static $y;
    /** @var \Closure[][] should not warn */
    public static $z;
}
