<?php

function test834(array $foo) {
    $resultArr = [
        'requests' => 0,
        'responsetime' => 123,
    ];

    // some foreach loop
    foreach ($foo as $_) {
        $resultArr['requests']++;
    }

    if ($resultArr["requests"] > 0) {  // should not emit PhanDivisionByZero
        $resultArr["responsetime"]    = 2 / $resultArr["requests"];
    }
}

function test834b() {
    $resultArr = [
        'requests' => 0,
        'foo' => 0,
    ];
    $resultArr['requests']++;
    --$resultArr['foo'];
    return $resultArr['requests'] > $resultArr['foo'];
}
