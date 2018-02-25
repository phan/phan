<?php

/** Test function */
function test(Type $arg = XYZ) : Ret {
    if ($arg instanceof Foo\Bar) {
        return test($arg->foo);
    } else {
        return $arg->bar;
    }
}
