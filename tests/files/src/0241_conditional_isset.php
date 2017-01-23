<?php

function foo(array $a) {
    $bar = [];

    foreach ($a as $b) {
        if (isset($prev)) {
            // prev must exist here
            $bar = $prev > $b ? $prev : $b;
        }
        // $prev must still be undefined
        echo $prev;
        $prev = $b;
    }
    return $bar;
}

foo([1,3,2]);
