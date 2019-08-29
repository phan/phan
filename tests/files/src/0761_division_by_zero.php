<?php
function f761() {
    $zero = 0;
    var_export([
        1/0,
        1%0,
        1%0.0,
        1%'0',
        1%$zero,
        1%(2-2),
        1%false,
        1%'',
        1%(2-2),
        1**(2-2),
    ]);
    $x = 3;
    $x %= 0;
    $y = 2;
    $y /= (1-1);
    $z = 3;
    $z **= false;
    var_export([$x, $y, $z]);
}
