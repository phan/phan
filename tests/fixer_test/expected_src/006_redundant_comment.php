<?php
namespace ns6;
use function rand;
use function var_export;
use function ns6\returns_int;

function returns_int() : int {
    return rand(0, 10);
}
var_export(returns_int());

$doubles_input =
    function (int $input) : int  {
        return $input * 2;
    };
var_export($doubles_input(21));

class C {
    public static function f($value) : void {
        var_export($value);
    }
}

C::f('F');
