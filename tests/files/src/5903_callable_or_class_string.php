<?php

/**
 * @template T
 * @param class-string<T>|callable(mixed ...$args):T $spec
 * @return T
 */
function create_object($spec) {
    return $GLOBALS['x'];
}

class Example5399 {}

$instance = create_object(Example5399::class);

/**
 * @phan-template T
 * @phan-param callable(mixed ...$args):T|array{class?:class-string<T>,args?:array,services?:array<string|null>} $spec
 * @phan-return T|object
 */
function create_object_from_spec($spec) {
    return $GLOBALS['x'];
}

if (rand(0, 1)) {
    $spec = [
        'class' => Example5399::class,
        'args' => [],
    ];
} else {
    $spec = [
        'class' => Example5399::class,
        'services' => [],
    ];
}

$transform = create_object_from_spec($spec);

/**
 * @phan-template T
 * @phan-param callable(mixed ...$args):T|array{class:class-string<T>} $spec
 * @phan-return T|object
 */
function create_object_from_simple_spec($spec) {
    return $GLOBALS['x'];
}

$simple_spec = [
    'class' => Example5399::class,
    'args' => [],
];
$transform2 = create_object_from_simple_spec($simple_spec);
