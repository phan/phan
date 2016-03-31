<?php

function f(int $p) {}

f([1, 2] + ['foo', 'bar']);
f([1, 2] + [3]);

class C {
    public $p = [];

    /** @var array */
    public $p_array = [];
}

$c = new C;
f($c->p + []);
f($c->p_array + []);
