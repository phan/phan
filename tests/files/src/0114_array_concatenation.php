<?php
function f(array $v) {}
f([1, 2] + [2, 3]);
f([2] + C::$array);
function g(int $p) {}
g([1, 2] + ['foo', 'bar']);
g([1, 2] + [3]);
class C {
    /** @var array */
    public static $array = [1];
}
$a = 2 + C::$array;
