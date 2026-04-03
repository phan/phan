<?php

namespace NS007;

class Foo {
    public const BAR = static function () {
        echo "Hello";
    };

    public const BAZ = static fn() => "World";
}

const MY_CLOSURE = static function () {
    return 42;
};

function test(\Closure $fn = static fn() => null) {}
