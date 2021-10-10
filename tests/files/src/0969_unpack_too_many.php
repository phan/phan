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
