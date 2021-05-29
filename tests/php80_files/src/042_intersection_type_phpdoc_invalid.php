<?php

/**
 * Phan should warn and not crash when passed invalid combinations of types in intersection types.
 * (the error messages in this test may change in the future)
 * @param Closure(int&string):(stdClass&ArrayObject) $value
 * @param int&null $other
 */
function test42($value, $other, object $x) {
    $value($x); // should not warn
    $value(1) // should warn
    $value(new stdClass()) // should warn
    $value($other) // should warn
    '@phan-debug-var $other';
}
test42(
    function (int $x): stdClass {
        return (object)['x' => $x];
    },
    null,
    new stdClass()
);
