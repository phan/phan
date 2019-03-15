<?php

/**
 * @param ?string $nullClassName
 * @param string|null $null
 * @param string|false $maybeFalse
 * @param string|array $array
 */
function test(string $className, $nullClassName, $null, $maybeFalse, $array) {
    $className::someMethod();
    $nullClassName::someMethod();  // emitting PhanNonClassMethodCall is inconsistent but pre-existing behavior
    $null::someMethod();
    $maybeFalse::someMethod();
    $array::someMethod();
}
