<?php

function null_handler(?int $a, ?int $b, string $default) {
    $a ??= $default;
    echo count($a);
    echo count($b ??= $default);
    echo count($b);
    $missing ??= [2];
    echo strlen($missing);
    $arr = [null, 2];
    $arr[0] ??= rand(0,10);
    echo strlen($arr);
}
