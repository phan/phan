<?php

function identity($x) {
    var_dump($x);
    return $x;
}

/**
 * @template T
 * @param Closure(int $i):T $x
 * @return array<int,T>
 */
function example_closure_usage(Closure $x) {
    $result = [];
    for ($i = 0; $i < 10; $i++) {
        $result[] = identity($x($i));
    }
    return $result;
}

// Warns about being passed an array of stdClass
echo strlen(example_closure_usage(function (int $i) {
    return (object)['key' => $i];
}));
echo strlen(example_closure_usage(function (int $i) : int {
    return rand(0,abs($i));
}));
