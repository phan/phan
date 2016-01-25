<?php
function f(array $v) {}
f([1, 2] + [2, 3]);

function g(int $p) {}
g([1, 2] + ['foo', 'bar']);
g([1, 2] + [3]);

