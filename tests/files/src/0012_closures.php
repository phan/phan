<?php

function f() : int {
    $alpha = 42;

    $closure = function(int $beta) use ($alpha) {
        return $alpha + $beta;
    };

    return $closure(42);
}
