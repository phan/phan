<?php

/**
 * @throws Exception
 */
function test_throw_expression(?string $nullableValue, bool $falsableValue, bool $condition, ?array $array) {
    // $value is non-nullable.
    $value = $nullableValue ?? throw new InvalidArgumentException();
    $x = throw new Exception();

    // $value is truthy.
    $value = $falsableValue ?: throw new InvalidArgumentException();

    // $value is only set if the array is not empty.
    $value = $array ? $array : throw new InvalidArgumentException();
    echo spl_object_hash($value);
    $value = $array ? throw new InvalidArgumentException() : $array;
    echo spl_object_hash($value);
    // There are other places where it could be used which are more controversial. These cases are allowed in this RFC.

    // An if statement could make the intention clearer
    $condition && throw new Exception();
    '@phan-debug-var $condition';
    $condition || throw new Exception();
    '@phan-debug-var $condition';
    $condition and throw new Exception();
    $condition or throw new Exception();
    if (rand(0, 1)) {
        throw throw new Exception();
    }
    for ($i = 0; $i < 10; throw new Exception("loop")) {
    }
}
$closure = fn() => throw new RuntimeException("unimplemented");
