<?php
namespace NS781;

class SomeCallable {
    public function __invoke(array $_) {
        echo "Called\n";
    }
}

function accepts_object(
    object $object,
    iterable $i,
    array $a,
    SomeCallable $c,
    string $s,
    int $int,
    callable $c2
) {
    var_export([$object, $i, $a, $c, $s, $int]);
}
function test_callable(callable $c) {
    // Some should not emit PhanTypeMismatchArgumentReal.
    // Can emit other warnings.
    // Not able to warn about the combination of assertions.
    accepts_object(
        $c,
        $c,
        $c,
        $c,
        $c,
        $c,
        $c
    );
}
function test_callable_array(callable $c) {
    // Should not emit PhanTypeMismatchArgumentReal.
    // Can emit other warnings.
    // Not able to warn about the combination of assertions.
    if (is_array($c)) {
        accepts_object(
            $c,
            $c,
            $c,
            $c,
            $c,
            $c,
            $c
        );
    }
}
function test_callable_string(callable $c) {
    // Should not emit PhanTypeMismatchArgumentReal.
    // Can emit other warnings.
    // Not able to warn about the combination of assertions.
    if (is_string($c)) {
        accepts_object(
            $c,
            $c,
            $c,
            $c,
            $c,
            $c,
            $c
        );
    }
}
function test_callable_object(callable $c) {
    // Should not emit PhanTypeMismatchArgumentReal.
    // Can emit other warnings.
    // Not able to warn about the combination of assertions.
    if (is_object($c)) {
        accepts_object(
            $c,
            $c,
            $c,
            $c,
            $c,
            $c,
            $c
        );
    }
}
