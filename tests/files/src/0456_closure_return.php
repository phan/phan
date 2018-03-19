<?php

function fn_456(int $x) { return "($x)"; }

/** @return \Closure(int):string */
function return_closure() {
    switch(rand(0,5)) {
        case 0:
            return function(int $x) : string {return "p$x"; };
        case 1:
            return function(int $x) : int {return $x * 2; };
        case 2:
            return Closure::fromCallable('fn_456');
        case 3:
            return Closure::fromCallable('strlen');
        default:
            return function() : string { return "Default"; };
    }
}
