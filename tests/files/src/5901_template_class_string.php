<?php

class ClassStringFoo {}

/**
 * @template T of object
 * @param class-string<T> $class
 * @return T
 */
function buildObject(string $class)
{
    return new $class();
}

/**
 * @param class-string $cls
 */
function demo(string $cls): object
{
    // Passing a class-string should satisfy the "object" template constraint.
    return buildObject($cls);
}

/** @var class-string $literal */
$literal = ClassStringFoo::class;
buildObject($literal);
