<?php

class A945 {
    public function __invoke(int $arg) {
    }
}
/**
 * @param callable-object&Countable $a
 * @param callable-object&Closure $b
 * @param callable-object&A945 $c
 * @param callable-object&stdClass $d
 *
 */
function test945($a, $b, $c, $d) {
    '@phan-debug-var $a, $b, $c, $d';
    $a();
    $b();
    $c(count($a));
    $d();
}
