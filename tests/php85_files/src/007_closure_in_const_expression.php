<?php

namespace NS007;

class Foo {
    public const BAR = static function () {
        echo "Hello";
    };

    public const BAZ = static function (): string {
        return "World";
    };
}

const MY_CLOSURE = static function (): int {
    return 42;
};

function test(\Closure $fn = static function () {}): void {
    $fn();
}

// These should still be errors even on PHP 8.5
class Invalid {
    // Non-static closure
    public const A = function () {};  // @phan-suppress-current-line PhanInvalidConstantExpression

    // Arrow function (not allowed)
    public const B = static fn() => 1;  // @phan-suppress-current-line PhanInvalidConstantExpression
}
