<?php

$class = new class {
    public function f() : int {
        return 42;
    }
};

function g(int $i) : int {
    return $i;
}

$x = g($class->f());
