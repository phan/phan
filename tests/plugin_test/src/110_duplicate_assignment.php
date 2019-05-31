<?php
function f110($x, array $y) {
    $x = $x;
    $y[0] =& $y[0];
    var_export([$x, $y]);
}
f110('x', [2]);
