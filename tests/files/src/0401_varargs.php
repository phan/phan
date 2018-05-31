<?php

function test_varargs401(stdClass $a1, stdClass ...$args) {
    var_export($args);
}

/** @param array<string,stdClass> $args */
function unpack_varargs401(array $args, iterable $iterable) {
    $c = [2];
    $int = 2;
    $stdClass = new stdClass();
    $arrayObject = new ArrayObject([new stdClass(), new stdClass()]);
    test_varargs401(...$args);
    test_varargs401(...$c);
    test_varargs401(...$int);
    test_varargs401(...$stdClass);
    test_varargs401(...$iterable);
    test_varargs401(...$arrayObject);
}
