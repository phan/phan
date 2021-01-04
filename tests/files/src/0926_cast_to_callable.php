<?php
namespace NS926;

/**
 * @param callable(int):bool $x
 */
function foo(callable $x): bool {
    return $x(1);
}

function test($x, $y, $z): void {
    if (is_callable($x) && is_object($x)) {
        foo($x);
    }
    if (is_callable($y) && is_object($y)) {
        foo($y);
    }
    if (is_callable($z) && is_object($z)) {
        foo($z);
    }
}
