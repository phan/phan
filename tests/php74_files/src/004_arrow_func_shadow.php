<?php
call_user_func(function () {
    $a = 2;
    $b = 2;  // should emit PhanUnusedVariable
    $d = 2;  // Currently does not warn, but low priority since PhanShadowedVariableInArrowFunc should catch most bugs.
    // Should emit PhanShadowedVariableInArrowFunc
    $x = fn() => ($d = 3);
    // Should also emit PhanShadowedVariableInArrowFunc
    $y = fn() => (($a = rand(0,9)) ? $a : 'nothing');
    $z = fn() => (($c = rand(0,9)) ?
        $c :
        'nothing');

    var_export($x());
    var_export($y());
    var_export($z());
    return ($outerVar = rand(0,9)) ? $outerVar : 'nothing';
});
