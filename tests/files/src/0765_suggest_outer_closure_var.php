<?php

function make_adder(int $a) : Closure {
    return function (int $b) {
        return $a + $b;
    };
}
var_export(make_adder(2)(2));
