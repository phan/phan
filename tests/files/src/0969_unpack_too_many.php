<?php
function foo(int $a) {
}
$x = [];
foo(...[]);
foo(...$x, ...$x);
$y = [1,2];
foo(...$y);
$z = rand(0, 1) > 0 ? [1,2] : [1,2,3];
foo(...$z);
function foo969(int $a, int $b) {
    var_dump($a, $b);
}
function variadic969(int ...$args) {
    foo969(...$args);
    if ($args) {
        foo969(...$args);
    }
}
variadic969(1, 2);

