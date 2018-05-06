<?php

/**
 * @phan-return Generator<int,string>
 */
function test_key_generator() : Generator {
    yield 2 => 3;
    yield 'x' => 'y';
    yield 3 => 'y';
    yield 'y' => new stdClass();
}

/**
 * @phan-return Generator<stdClass>
 */
function test_generator() : Generator {
    yield new stdClass();
    yield;
    yield 2 => null;
    yield 'x' => new stdClass();  // The expected key type is unspecified, so don't warn about the key?
    yield 2;

    yield from test_key_generator();
}

/**
 * @phan-return Generator<void,stdClass>
 */
function test_void_generator() : Generator {
    yield 'x' => new stdClass();
    yield new stdClass();
}

/**
 * @return Generator|int[]
 */
function test_alternate_generator_syntax() {
    yield;
    yield null;
    yield 2;
}
