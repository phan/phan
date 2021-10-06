<?php

function example968() {
    $var = 123;  // properly warns
    $x = null;
    $value = 123;
    try {
        $x = 123;
    } catch (Exception $e) {
        $var = 123;
        var_dump($value);
        throw $e;
    }
    var_dump($var);
    return $x;
}

function example968b($x) {
    try {
        $x = 123;
        return;
    } catch (Exception $e) {
        $x = 456;
    }
    var_dump($x);
}

example968();
example968b(2);
