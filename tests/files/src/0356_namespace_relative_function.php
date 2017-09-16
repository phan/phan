<?php

namespace {
    function test356(string $x) {
    }

    \test356(2);  // should warn about types
    test356(2);  // should warn about types
    namespace\test356(2);  // should warn about types
}

namespace NS355 {
    use function test356;
    \test356(2);  // should warn about types
    test356(2);  // should warn about types
    namespace\test356(2);  // should fail

    function inNS356(int $y) {
        echo "$y\n";
    }
    inNS356(42);
    namespace\inNS356(42);
    \inNS356(42);  // should fail.
}
