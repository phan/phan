<?php

/**
 * @param Closure():(string[]) $c
 * @param (Closure():string)[] $a
 */
function test_closures(Closure $c, array $a) {
}
test_closures([1], [2]);
/**
 * @param callable():(string[]) $c
 * @param (callable():string)[] $a
 */
function test_callables(callable $c, array $a) {
}
test_callables([1], [2]);
