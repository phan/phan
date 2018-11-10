<?php

/**
 * @param ?string $nullMethodName
 * @param callable|false $maybeFalse
 * @param null $null
 * @param string|array<string,mixed> $array
 * @param array<string,mixed> $onlyArray
 * @param string|null $maybeNull
 * @param string|int $maybeInt
 * @param callable|object $maybeObject
 * @param array{key:string} $arrayShape
 * @param array{0:string,1:string} $callableArrayShape
 * @param mixed $mixed
 * @suppress PhanUnreferencedFunction
 */
function test_strict_function_call(
    string $methodName,
    $nullMethodName,
    $maybeFalse,
    $null,
    $array,
    $onlyArray,
    $maybeNull,
    $maybeInt,
    $maybeObject,
    $arrayShape,
    $callableArrayShape,
    $mixed
) {
    'strlen'();  // too few args
    $methodName();
    $nullMethodName();  // possibly invalid
    $null();  // definitely invalid
    $maybeFalse();  // possibly invalid
    $array();  // should warn
    $onlyArray();  // should warn
    $maybeNull();  // should warn
    $v = $maybeInt();  // should warn
    var_export($v);
    $maybeObject();  // should not warn
    $arrayShape();  // should warn
    $callableArrayShape();  // should not warn
    (2)();
    (2.3)();
    $mixed();
}
