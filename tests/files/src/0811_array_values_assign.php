<?php

namespace NS811;

interface Examples {
    public function returnsIterable() : iterable;
    public function returnsArray() : array;
    public function &returnsArrayRef() : array;
    public function returnsObject() : object;
}
class Test {
    public static function returnsArray() : array {
        return [0];
    }
}

function test_assign(array $a, Examples $e, int $i) {
    array_values($a)[0] = 3;
    array_values($a) = 3;
    $e->returnsArray()[] = 3;  // should warn
    $e->returnsArray() = null;  // should warn
    $e->returnsArray()[$i] = 3;  // should warn
    $e->returnsArray()[$i][0] = 3;  // should not warn
    $e->returnsArrayRef()[$i] = 3;  // should not warn
    Test::returnsArray()[0] = 42;
    $e->returnsObject()[$i] = 3;  // should not warn
    $e->returnsObject() = 3;  // should not warn
}
