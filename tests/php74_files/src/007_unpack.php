<?php
call_user_func(function () {
    $arr1 = [3, 4];
    $arr2 = [1, 2, ...$arr1, 5];
    $arr3 = [...[1], ...[], 2];
    $x = [];
    $arr4 = [...$x, ...$x];
    echo strlen($arr2);
    echo strlen($arr3);
    echo strlen($arr4);
});
