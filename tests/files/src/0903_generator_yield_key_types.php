<?php

/**
 * @return Generator<int, string>
 */
function validIntKeyGenerator(): Generator {
    yield 'test1';           // Valid: auto-increments to 0
    yield 1 => 'test2';      // Valid: explicit int key
    yield 'test3';           // Valid: auto-increments to 2
    yield 10 => 'test4';     // Valid: explicit int key
    yield 'test5';           // Valid: auto-increments to 11
}

/**
 * @return Generator<string, int>
 */
function invalidStringKeyGenerator(): Generator {
    yield 100;               // Invalid: int key when string expected
    yield 'key' => 200;      // Valid: explicit string key
    yield 300;               // Invalid: int key when string expected
}

/**
 * @return Generator<int|string, mixed>
 */
function mixedKeyGenerator(): Generator {
    yield 'test1';           // Valid: int key is part of int|string
    yield 'key' => 'test2';  // Valid: string key is part of int|string
    yield 123 => 'test3';    // Valid: int key is part of int|string
}

/**
 * @return Generator<string, string>
 */
function explicitStringKeyGenerator(): Generator {
    yield 'key1' => 'value1';  // Valid: explicit string key
    yield 'key2' => 'value2';  // Valid: explicit string key
}

/**
 * @return Generator<int, string>
 */
function invalidExplicitStringKey(): Generator {
    yield 'str_key' => 'value';  // Invalid: string key when int expected
}

/**
 * @return Generator<mixed, string>
 */
function mixedKeyAllowed(): Generator {
    yield 'test1';             // Valid: int key is part of mixed
    yield 'key' => 'test2';    // Valid: string key is part of mixed
    yield 123 => 'test3';      // Valid: int key is part of mixed
}
